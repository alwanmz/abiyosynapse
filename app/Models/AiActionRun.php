<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionRun extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'user_id',
        'tool',
        'instruction',
        'status',
        'arguments',
        'result',
        'error_message',
        'confirmed_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result' => 'array',
            'confirmed_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
