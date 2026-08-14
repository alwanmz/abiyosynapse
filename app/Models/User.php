<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable
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
        'role_id',
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
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function getRoleDisplayName(): ?string
    {
        return $this->role?->display_name;
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
     * Check if this user has the admin role.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role?->name, ['super_admin', 'admin'], true);
    }

    /**
     * Check if this user is a member of the given team.
     */
    public function isInTeam(Team $team): bool
    {
        return Cache::remember(
            "user_{$this->id}_in_team_{$team->id}",
            300,
            fn() => $this->teams()->where('teams.id', $team->id)->exists()
        );
    }

    public function hasPermissionTo(string $permissionName): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->role) {
            return false;
        }

        // We should eagerly load permissions or rely on caching if possible
        // For now, load if missing
        if (!$this->role->relationLoaded('permissions')) {
            $this->role->load('permissions');
        }

        return $this->role->hasPermissionTo($permissionName);
    }
}
