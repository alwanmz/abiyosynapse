<?php

namespace App\Services\Onboarding;

use App\Support\AccountRole;
use App\Support\DefaultChartOfAccounts;

/**
 * Normalizes and validates a chart-of-accounts draft coming from the AI, an
 * uploaded template or the wizard editor. Rows are
 * {code, name, type, normal_balance, is_postable, parent_code, report_line}.
 */
class CoaDraftValidator
{
    public const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    private const TYPE_ALIASES = [
        'aset' => 'asset', 'aktiva' => 'asset', 'harta' => 'asset',
        'kewajiban' => 'liability', 'liabilitas' => 'liability', 'utang' => 'liability', 'hutang' => 'liability',
        'modal' => 'equity', 'ekuitas' => 'equity',
        'pendapatan' => 'revenue', 'penghasilan' => 'revenue', 'income' => 'revenue',
        'beban' => 'expense', 'biaya' => 'expense',
    ];

    private const DEFAULT_REPORT_LINES = [
        'asset' => 'other_current_assets',
        'liability' => 'other_current_liabilities',
        'equity' => 'other_equity',
        'revenue' => 'other_revenue',
        'expense' => 'operating_expenses',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{accounts: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function normalize(array $rows): array
    {
        $accounts = [];
        $errors = [];
        $seen = [];

        foreach (array_values($rows) as $index => $row) {
            $line = $index + 1;
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $type = $this->type($row['type'] ?? null);

            if ($code === '' && $name === '') {
                continue;
            }

            if ($code === '' || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9.\-]{0,29}$/', $code)) {
                $errors[] = "Baris {$line}: kode akun \"{$code}\" tidak valid (huruf/angka/titik/strip, maks. 30 karakter).";
                continue;
            }

            if (isset($seen[$code])) {
                $errors[] = "Baris {$line}: kode akun {$code} dipakai lebih dari sekali.";
                continue;
            }

            if ($name === '' || mb_strlen($name) > 255) {
                $errors[] = "Baris {$line}: nama akun {$code} wajib diisi (maks. 255 karakter).";
            }

            if ($type === null) {
                $errors[] = "Baris {$line}: tipe akun {$code} harus salah satu dari Aset, Kewajiban, Modal, Pendapatan, Beban.";
                $type = 'asset';
            }

            $isPostable = $this->isPostable($row);
            $reportLine = $this->reportLine($row['report_line'] ?? null, $type, $isPostable);

            if ($reportLine === false) {
                $errors[] = "Baris {$line}: pos laporan \"{$row['report_line']}\" tidak cocok untuk akun {$code} bertipe {$type}.";
                $reportLine = self::DEFAULT_REPORT_LINES[$type];
            }

            $parentCode = trim((string) ($row['parent_code'] ?? ''));

            $seen[$code] = true;
            $accounts[] = [
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'normal_balance' => $this->normalBalance($row['normal_balance'] ?? null, $type),
                'is_postable' => $isPostable,
                'parent_code' => $parentCode === '' ? null : $parentCode,
                'report_line' => $reportLine,
            ];
        }

        $errors = [...$errors, ...$this->structureErrors($accounts)];

        return ['accounts' => $this->sortParentsFirst($accounts), 'errors' => $errors];
    }

    /**
     * @param  array<int, array<string, mixed>>  $accounts  normalized accounts
     * @param  array<string, mixed>  $mapping  role => account code
     * @return array<string, string> role => error
     */
    public function mappingErrors(array $accounts, array $mapping): array
    {
        $byCode = collect($accounts)->keyBy('code');
        $errors = [];

        foreach (AccountRole::cases() as $role) {
            $code = trim((string) ($mapping[$role->value] ?? ''));
            $account = $byCode->get($code);

            if ($code === '' || $account === null) {
                $errors[$role->value] = "Pilih akun untuk \"{$role->label()}\".";
                continue;
            }

            if (! in_array($account['type'], $role->allowedTypes(), true)) {
                $errors[$role->value] = "Akun {$code} bertipe {$account['type']}, sedangkan \"{$role->label()}\" butuh akun bertipe " . implode('/', $role->allowedTypes()) . '.';
                continue;
            }

            if (! $role->isParentRole() && ! $account['is_postable']) {
                $errors[$role->value] = "Akun {$code} adalah akun header; \"{$role->label()}\" butuh akun yang bisa diposting.";
            }
        }

        return $errors;
    }

    /**
     * Best-effort mapping suggestion: keep valid suggestions, fall back to
     * the standard codes when present.
     *
     * @param  array<int, array<string, mixed>>  $accounts
     * @param  array<string, mixed>  $suggested
     * @return array<string, string>
     */
    public function suggestMapping(array $accounts, array $suggested = []): array
    {
        $mapping = [];
        $defaults = DefaultChartOfAccounts::roleMapping();

        foreach (AccountRole::cases() as $role) {
            $mapping[$role->value] = '';

            foreach ([$suggested[$role->value] ?? null, $defaults[$role->value]] as $candidate) {
                if (is_string($candidate)
                    && ! isset($this->mappingErrors($accounts, [$role->value => $candidate])[$role->value])) {
                    $mapping[$role->value] = $candidate;
                    break;
                }
            }
        }

        return $mapping;
    }

    /** @return array<int, string> */
    private function structureErrors(array $accounts): array
    {
        $errors = [];
        $byCode = collect($accounts)->keyBy('code');

        foreach ($accounts as $account) {
            if ($account['parent_code'] === null) {
                continue;
            }

            $parent = $byCode->get($account['parent_code']);

            if ($parent === null) {
                $errors[] = "Akun {$account['code']}: kode induk {$account['parent_code']} tidak ditemukan.";
            } elseif ($parent['type'] !== $account['type']) {
                $errors[] = "Akun {$account['code']}: tipe harus sama dengan akun induk {$parent['code']} ({$parent['type']}).";
            } elseif ($parent['code'] === $account['code']) {
                $errors[] = "Akun {$account['code']} tidak boleh menjadi induk dirinya sendiri.";
            }
        }

        foreach ($accounts as $account) {
            $visited = [];
            $cursor = $account;
            while ($cursor !== null && $cursor['parent_code'] !== null) {
                if (isset($visited[$cursor['code']])) {
                    $errors[] = "Akun {$account['code']}: hubungan induk membentuk lingkaran.";
                    break;
                }
                $visited[$cursor['code']] = true;
                $cursor = $byCode->get($cursor['parent_code']);
            }
        }

        $postableTypes = collect($accounts)->where('is_postable', true)->pluck('type')->unique();
        foreach (self::TYPES as $type) {
            if (! $postableTypes->contains($type)) {
                $errors[] = "COA harus punya minimal satu akun {$type} yang bisa diposting.";
            }
        }

        return array_values(array_unique($errors));
    }

    private function type(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, self::TYPES, true) ? $value : (self::TYPE_ALIASES[$value] ?? null);
    }

    private function normalBalance(mixed $value, string $type): string
    {
        $value = strtolower(trim((string) $value));

        return match (true) {
            in_array($value, ['debit', 'd', 'db'], true) => 'debit',
            in_array($value, ['credit', 'kredit', 'k', 'cr'], true) => 'credit',
            default => in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit',
        };
    }

    private function isPostable(array $row): bool
    {
        if (array_key_exists('is_header', $row)) {
            return ! $this->truthy($row['is_header']);
        }

        return array_key_exists('is_postable', $row) ? $this->truthy($row['is_postable']) : true;
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'y', 'yes'], true);
    }

    private function reportLine(mixed $value, string $type, bool $isPostable): string|false|null
    {
        if (! $isPostable) {
            return null;
        }

        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return self::DEFAULT_REPORT_LINES[$type];
        }

        $expectedReport = in_array($type, ['revenue', 'expense'], true) ? 'profit_loss' : 'balance_sheet';

        return DefaultChartOfAccounts::reportCodeFor($value) === $expectedReport ? $value : false;
    }

    /** @return array<int, array<string, mixed>> */
    private function sortParentsFirst(array $accounts): array
    {
        $byCode = collect($accounts)->keyBy('code');
        $depth = function (array $account) use ($byCode): int {
            $level = 0;
            $cursor = $account;
            while ($cursor['parent_code'] !== null && ($cursor = $byCode->get($cursor['parent_code'])) !== null && $level < 50) {
                $level++;
            }

            return $level;
        };

        return collect($accounts)
            ->sortBy([fn ($a, $b) => $depth($a) <=> $depth($b), fn ($a, $b) => strnatcmp($a['code'], $b['code'])])
            ->values()
            ->all();
    }
}
