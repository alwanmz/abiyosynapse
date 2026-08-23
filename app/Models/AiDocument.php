<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDocument extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'uploaded_by',
        'document_type',
        'original_filename',
        'storage_path',
        'mime_type',
        'file_size',
        'sha256',
        'status',
        'provider',
        'model',
        'extracted_payload',
        'normalized_payload',
        'confidence_payload',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'extracted_payload' => 'array',
            'normalized_payload' => 'array',
            'confidence_payload' => 'array',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, ['review', 'failed'], true);
    }
}
