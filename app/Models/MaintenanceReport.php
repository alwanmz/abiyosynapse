<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceReport extends Model
{
    protected $fillable = [
        'client_id',
        'project_id',
        'created_by',
        'report_number',
        'letter_number',
        'letter_date',
        'recipient_name',
        'recipient_title',
        'recipient_address',
        'title',
        'period_start',
        'period_end',
        'summary',
        'signed_by_name',
        'signed_by_role',
        'signature_path',
        'status',
        'published_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'letter_date' => 'date',
        'published_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceReportItem::class)->orderBy('order')->orderBy('id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Filename stem shared by the DOCX and XLSX exports.
     */
    public function exportFilename(string $extension): string
    {
        $stem = str_replace(['/', '\\', ' '], '-', $this->report_number);

        return "Laporan-Maintenance-{$stem}.{$extension}";
    }
}
