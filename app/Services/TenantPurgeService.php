<?php

namespace App\Services;

use App\Models\Company;
use App\Models\MarketingLead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Permanently removes a tenant: every company-scoped row (and rows that hang
 * off them), stored files, and the members who belong to no other company.
 * Removed members are kept as marketing leads so they cannot start a second
 * free trial and can be contacted later.
 */
class TenantPurgeService
{
    /** @var array<string, array<int, array{column: string, table: string}>>|null */
    private ?array $foreignKeys = null;

    /** @return array{company: string, rows: int, users_removed: array<int, string>} */
    public function purge(Company $company, string $reason = 'trial_expired'): array
    {
        $company->loadMissing('subscription');
        $memberIds = DB::table('company_user')->where('company_id', $company->id)->pluck('user_id');
        $removableUsers = User::whereIn('id', $memberIds)
            ->where(fn ($query) => $query->whereNull('is_system')->orWhere('is_system', false))
            ->whereDoesntHave('companies', fn ($query) => $query->where('companies.id', '!=', $company->id))
            ->get();

        $files = $this->collectFiles($company, $removableUsers->pluck('id')->all());
        $rows = 0;

        DB::transaction(function () use ($company, $removableUsers, $reason, &$rows): void {
            foreach ($removableUsers as $user) {
                MarketingLead::updateOrCreate(['email' => strtolower($user->email)], [
                    'name' => $user->name,
                    'company_name' => $company->name,
                    'industry' => $company->industry,
                    'source' => $reason,
                    'trial_started_at' => $company->subscription?->starts_at ?? $company->created_at,
                    'trial_ended_at' => $company->subscription?->trial_ends_at ?? $company->trial_ends_at,
                    'purged_at' => now(),
                    'meta' => [
                        'company_code' => $company->code,
                        'business_type' => $company->business_type,
                        'username' => $user->username,
                        'was_owner' => $company->owner_id === $user->id,
                    ],
                ]);
            }

            foreach (DB::table('users')->where('current_company_id', $company->id)->pluck('id') as $userId) {
                DB::table('users')->where('id', $userId)->update([
                    'current_company_id' => DB::table('company_user')
                        ->where('user_id', $userId)
                        ->where('company_id', '!=', $company->id)
                        ->value('company_id'),
                ]);
            }

            foreach ($this->deletionPlan() as $table => $condition) {
                $rows += $condition($table, DB::table($table), $company->id)->delete();
            }

            $rows += DB::table('companies')->where('id', $company->id)->delete();

            foreach ($removableUsers as $user) {
                $this->removeUser($user);
            }
        });

        foreach ($files as [$disk, $path]) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $summary = [
            'company' => "{$company->id}:{$company->code}",
            'rows' => $rows,
            'users_removed' => $removableUsers->pluck('email')->all(),
        ];
        Log::info('Tenant purged', $summary + ['reason' => $reason]);

        return $summary;
    }

    private function removeUser(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $user->id)->delete();

        try {
            // Savepoint: rows in other tenants may still reference this user.
            DB::transaction(fn () => DB::table('users')->where('id', $user->id)->delete());
        } catch (Throwable $exception) {
            report($exception);
            DB::table('users')->where('id', $user->id)->update([
                'email' => "purged-{$user->id}@invalid.nexumi",
                'username' => "purged-{$user->id}",
                'password' => bcrypt(bin2hex(random_bytes(32))),
                'current_company_id' => null,
            ]);
        }
    }

    /**
     * Every table that holds company data, ordered so rows are deleted before
     * the rows they reference. Tables without company_id are reached through
     * their foreign key to a company-scoped parent (document lines etc.).
     *
     * @return array<string, callable(string, \Illuminate\Database\Query\Builder, int): \Illuminate\Database\Query\Builder>
     */
    private function deletionPlan(): array
    {
        $tables = collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn (string $table) => in_array($table, ['companies', 'migrations', 'users'], true))
            ->values();

        $conditions = [];
        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'company_id')) {
                $conditions[$table] = fn (string $t, $query, int $companyId) => $query->where("{$t}.company_id", $companyId);
            }
        }

        // Reach child tables through their FK to an already scoped parent.
        do {
            $added = false;
            foreach ($tables as $table) {
                if (isset($conditions[$table])) {
                    continue;
                }
                foreach ($this->foreignKeys()[$table] ?? [] as $fk) {
                    if (isset($conditions[$fk['table']]) && $fk['table'] !== $table) {
                        $parent = $fk['table'];
                        $parentCondition = $conditions[$parent];
                        $conditions[$table] = fn (string $t, $query, int $companyId) => $query->whereIn(
                            "{$t}.{$fk['column']}",
                            fn ($sub) => $parentCondition($parent, $sub->select("{$parent}.id")->from($parent), $companyId),
                        );
                        $added = true;
                        break;
                    }
                }
            }
        } while ($added);

        return $this->childrenFirst($conditions);
    }

    /**
     * @param  array<string, callable>  $conditions
     * @return array<string, callable>
     */
    private function childrenFirst(array $conditions): array
    {
        $pending = array_keys($conditions);
        $ordered = [];

        while ($pending !== []) {
            $progress = false;
            foreach ($pending as $index => $table) {
                $referencedByPending = collect($pending)->contains(fn (string $other) => $other !== $table
                    && collect($this->foreignKeys()[$other] ?? [])->contains(fn ($fk) => $fk['table'] === $table));

                if (! $referencedByPending) {
                    $ordered[$table] = $conditions[$table];
                    unset($pending[$index]);
                    $progress = true;
                }
            }

            if (! $progress) {
                // FK cycle: fall back to the remaining order and rely on cascades.
                foreach ($pending as $table) {
                    $ordered[$table] = $conditions[$table];
                }
                break;
            }
        }

        return $ordered;
    }

    /** @return array<string, array<int, array{column: string, table: string}>> */
    private function foreignKeys(): array
    {
        if ($this->foreignKeys !== null) {
            return $this->foreignKeys;
        }

        $this->foreignKeys = [];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (Schema::getForeignKeys($table) as $fk) {
                if (count($fk['columns']) === 1) {
                    $this->foreignKeys[$table][] = ['column' => $fk['columns'][0], 'table' => $fk['foreign_table']];
                }
            }
        }

        return $this->foreignKeys;
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, array{0: string, 1: string}>
     */
    private function collectFiles(Company $company, array $userIds): array
    {
        $files = [];

        if ($company->logo_path) {
            $files[] = ['public', $company->logo_path];
        }

        if (Schema::hasTable('ai_documents')) {
            foreach (DB::table('ai_documents')->where('company_id', $company->id)->whereNotNull('storage_path')->pluck('storage_path') as $path) {
                $files[] = ['local', $path];
            }
        }

        foreach (DB::table('users')->whereIn('id', $userIds)->whereNotNull('avatar_path')->pluck('avatar_path') as $path) {
            $files[] = ['public', $path];
        }

        return $files;
    }
}
