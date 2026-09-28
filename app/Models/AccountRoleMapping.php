<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\AccountRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountRoleMapping extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'role',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'role' => AccountRole::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
