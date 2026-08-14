<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceReportItem extends Model
{
    protected $fillable = [
        'maintenance_report_id',
        'ticket_id',
        'category',
        'found_at',
        'description',
        'resolution',
        'status_result',
        'resolved_at',
        'notes',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
        'found_at' => 'date',
        'resolved_at' => 'date',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MaintenanceReport::class, 'maintenance_report_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
