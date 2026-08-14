<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model implements Authenticatable
{
    use AuthenticatableTrait;
    use SoftDeletes;

    protected $fillable = [
        'kode',
        'nama',
        'alamat',
        'director_name',
        'director_title',
        'kontak',
        'username',
        'email',
        'deskripsi',
        'is_active',
        'monthly_request_quota',
        'request_quota_unlimited',
        'telegram_verification_code',
        'telegram_verification_code_generated_at',
    ];

    protected $hidden = [
        'password',
        'portal_password_plain',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'request_quota_unlimited' => 'boolean',
        'monthly_request_quota' => 'integer',
        'password' => 'hashed',
        'telegram_verification_code_generated_at' => 'datetime',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(ClientActionLog::class);
    }

    public function telegramContacts(): HasMany
    {
        return $this->hasMany(TelegramContact::class);
    }

    public function ticketComments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function requestQuota(): HasOne
    {
        return $this->hasOne(ClientRequestQuota::class);
    }

    /**
     * Whether this client may raise feature requests without any monthly cap.
     */
    public function isRequestUnlimited(): bool
    {
        return (bool) $this->request_quota_unlimited;
    }

    /**
     * Monthly feature-request allowance, falling back to the app-wide default.
     */
    public function monthlyRequestQuota(): int
    {
        return (int) ($this->monthly_request_quota ?? config('tickets.portal.monthly_request_quota', 10));
    }

    public function getHasPortalPasswordAttribute(): bool
    {
        return ! empty($this->password);
    }

    public function regenerateLoginPassword(): string
    {
        $password = Str::password(12);
        $this->forceFill([
            'password' => $password,
            'portal_password_plain' => $password,
        ])->save();

        return $password;
    }

    public function regenerateTelegramVerificationCode(): string
    {
        $code = strtoupper(Str::random(8));

        $this->update([
            'telegram_verification_code' => $code,
            'telegram_verification_code_generated_at' => now(),
        ]);

        return $code;
    }
}
