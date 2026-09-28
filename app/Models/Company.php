<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory;

    public const TRIAL_GRACE_DAYS = 7;

    public const BUSINESS_TYPES = ['jasa', 'dagang', 'manufaktur', 'campuran'];

    protected $fillable = [
        'industry',
        'business_type',
        'business_scale',
        'business_description',
        'uses_inventory',
        'is_pkp',
        'onboarded_at',
        'name',
        'legal_name',
        'code',
        'tax_id',
        'address',
        'logo_path',
        'currency',
        'reporting_standard',
        'reporting_language',
        'presentation_currency',
        'comparative_period_enabled',
        'fiscal_year_start_month',
        'is_active',
        'trial_ends_at',
        'owner_id',
        'status',
        'suspended_at',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'fiscal_year_start_month' => 'integer',
            'trial_ends_at' => 'datetime',
            'comparative_period_enabled' => 'boolean',
            'suspended_at' => 'datetime',
            'uses_inventory' => 'boolean',
            'is_pkp' => 'boolean',
            'onboarded_at' => 'datetime',
        ];
    }

    public function accountRoleMappings(): HasMany
    {
        return $this->hasMany(AccountRoleMapping::class);
    }

    public function subscriptionOrders(): HasMany
    {
        return $this->hasMany(SubscriptionOrder::class);
    }

    /**
     * Null trial_ends_at means "not on a trial" (never was, or trial
     * enforcement predates this company) — never expired in that case.
     * A paid subscription is never considered trial-expired.
     */
    public function isTrialExpired(): bool
    {
        if ($this->subscription?->isPaid()) {
            return false;
        }

        $trialEndsAt = $this->subscription?->trial_ends_at ?? $this->trial_ends_at;

        return $trialEndsAt !== null && $trialEndsAt->isPast();
    }

    public function trialPurgeDate(): ?\Carbon\CarbonInterface
    {
        $trialEndsAt = $this->subscription?->trial_ends_at ?? $this->trial_ends_at;

        return $trialEndsAt?->copy()->addDays(self::TRIAL_GRACE_DAYS);
    }

    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }

    public function isOperational(): bool
    {
        return $this->is_active
            && $this->status === 'active'
            && ($this->subscription?->isOperational() ?? true);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->using(CompanyUser::class)
            ->withPivot(['role_id', 'is_default', 'joined_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(CompanySubscription::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function currencies(): HasMany
    {
        return $this->hasMany(CompanyCurrency::class);
    }

    public function currencyRates(): HasMany
    {
        return $this->hasMany(CurrencyRate::class);
    }

    public function reportingMappings(): HasMany
    {
        return $this->hasMany(ReportingAccountMapping::class);
    }

    public function presentationCurrencyCode(): string
    {
        return strtoupper((string) ($this->presentation_currency ?: $this->currency));
    }
}
