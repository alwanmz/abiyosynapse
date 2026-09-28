<?php

namespace App\Console\Commands;

use App\Mail\TrialLifecycleMail;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\User;
use App\Services\TenantLifecycleService;
use App\Services\TenantPurgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcessTenantLifecycle extends Command
{
    protected $signature = 'tenants:process-lifecycle {--dry-run : Show what would happen without changing anything}';

    protected $description = 'Send trial reminders, lock expired trials and lapsed subscriptions, and purge unpaid trials after the grace period';

    public function handle(TenantLifecycleService $lifecycle, TenantPurgeService $purger): int
    {
        $dryRun = (bool) $this->option('dry-run');

        Company::query()->with(['subscription', 'owner'])->orderBy('id')->each(function (Company $company) use ($lifecycle, $purger, $dryRun): void {
            try {
                $subscription = $company->subscription ?? ($dryRun ? null : $lifecycle->provisionSubscription($company));

                if ($subscription === null) {
                    return;
                }

                $subscription->isPaid()
                    ? $this->handlePaid($company, $subscription, $dryRun)
                    : $this->handleTrial($company, $subscription, $purger, $dryRun);
            } catch (Throwable $exception) {
                report($exception);
                $this->error("[{$company->code}] gagal: {$exception->getMessage()}");
            }
        });

        return self::SUCCESS;
    }

    private function handleTrial(Company $company, CompanySubscription $subscription, TenantPurgeService $purger, bool $dryRun): void
    {
        $trialEndsAt = $subscription->trial_ends_at ?? $company->trial_ends_at;

        if ($trialEndsAt === null) {
            return;
        }

        $purgeAt = $trialEndsAt->copy()->addDays(Company::TRIAL_GRACE_DAYS);

        if ($purgeAt->isPast()) {
            $this->line("[{$company->code}] hapus data (trial berakhir {$trialEndsAt->toDateString()})");
            if (! $dryRun) {
                $summary = $purger->purge($company);
                $this->line("  {$summary['rows']} baris dihapus, user: " . implode(', ', $summary['users_removed']));
            }

            return;
        }

        if ($trialEndsAt->isPast()) {
            if ($subscription->status === 'trialing') {
                $this->line("[{$company->code}] trial berakhir, dikunci sampai bayar (hapus {$purgeAt->toDateString()})");
                if (! $dryRun) {
                    $subscription->update(['status' => 'past_due']);
                }
            }
            $this->notifyOnce($company, $subscription, 'expired', $trialEndsAt, $purgeAt, $dryRun);

            return;
        }

        $hoursLeft = now()->diffInHours($trialEndsAt);
        if ($hoursLeft <= 24) {
            $this->notifyOnce($company, $subscription, 'reminder_1d', $trialEndsAt, $purgeAt, $dryRun);
        } elseif ($hoursLeft <= 72) {
            $this->notifyOnce($company, $subscription, 'reminder_3d', $trialEndsAt, $purgeAt, $dryRun);
        }
    }

    private function handlePaid(Company $company, CompanySubscription $subscription, bool $dryRun): void
    {
        if ($subscription->isPeriodEnded() && $subscription->status === 'active') {
            $this->line("[{$company->code}] periode langganan berakhir {$subscription->current_period_end->toDateString()}, dikunci");
            if (! $dryRun) {
                $subscription->update(['status' => 'past_due']);
            }
        }
    }

    private function notifyOnce(Company $company, CompanySubscription $subscription, string $key, $trialEndsAt, $purgeAt, bool $dryRun): void
    {
        $metadata = $subscription->metadata ?? [];

        if (isset($metadata['notifications'][$key])) {
            return;
        }

        $recipients = $this->recipients($company);
        $this->line("[{$company->code}] email {$key} ke " . ($recipients->pluck('email')->implode(', ') ?: '(tidak ada penerima)'));

        if ($dryRun) {
            return;
        }

        foreach ($recipients as $user) {
            try {
                Mail::to($user->email)->send(new TrialLifecycleMail(
                    $key === 'expired' ? 'expired' : 'reminder',
                    $user->name,
                    $company->name,
                    $trialEndsAt,
                    $purgeAt,
                ));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $metadata['notifications'][$key] = now()->toIso8601String();
        $subscription->update(['metadata' => $metadata]);
    }

    /** @return Collection<int, User> */
    private function recipients(Company $company): Collection
    {
        $admins = User::query()
            ->whereHas('companies', fn ($query) => $query->where('companies.id', $company->id))
            ->whereIn('id', fn ($query) => $query->select('company_user.user_id')
                ->from('company_user')
                ->join('roles', 'roles.id', '=', 'company_user.role_id')
                ->where('company_user.company_id', $company->id)
                ->whereIn('roles.name', ['super_admin', 'admin']))
            ->get();

        return $admins->push(...array_filter([$company->owner]))->unique('id')->values();
    }
}
