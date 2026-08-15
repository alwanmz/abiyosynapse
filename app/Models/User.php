<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'avatar_path',
    ];

    /**
     * Always expose `avatar_url` to JSON so the frontend can render the
     * photo without having to know our storage layout.
     *
     * @var list<string>
     */
    protected $appends = ['avatar_url'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'email_otp_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'email_otp_expires_at' => 'datetime',
        ];
    }

    private ?CompanyUser $membershipCache = null;

    private bool $membershipCacheLoaded = false;

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->using(CompanyUser::class)
            ->withPivot(['role_id', 'is_default', 'joined_at'])
            ->withTimestamps();
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    /**
     * Membership row (with role) for the company currently active on this
     * user. Memoized per-instance since `hasPermissionTo()`/`isAdmin()` can
     * be called several times in a single request.
     */
    public function currentCompanyMembership(): ?CompanyUser
    {
        if ($this->membershipCacheLoaded) {
            return $this->membershipCache;
        }

        $this->membershipCacheLoaded = true;

        if (! $this->current_company_id) {
            return $this->membershipCache = null;
        }

        return $this->membershipCache = CompanyUser::query()
            ->where('user_id', $this->id)
            ->where('company_id', $this->current_company_id)
            ->with('role.permissions')
            ->first();
    }

    /**
     * Public URL for the user's avatar, or null when none is set.
     *
     * Falls back to null on the frontend, where the <Avatar> component
     * renders the user's initials as the placeholder.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path);
    }

    /**
     * Check if this user has the admin role in the currently active company.
     */
    public function isAdmin(): bool
    {
        $role = $this->currentCompanyMembership()?->role;

        return in_array($role?->name, ['super_admin', 'admin'], true);
    }

    public function hasPermissionTo(string $permissionName): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $role = $this->currentCompanyMembership()?->role;

        if (! $role) {
            return false;
        }

        if (! $role->relationLoaded('permissions')) {
            $role->load('permissions');
        }

        return $role->hasPermissionTo($permissionName);
    }
}
