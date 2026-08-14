<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'client_id',
        'project_id',
        'timeline_id',
        'reporter_id',
        'assigned_to',
        'qa_assigned_to',
        'title',
        'description',
        'ticket_number',
        'source',
        'external_reporter_name',
        'external_reporter_contact',
        'telegram_chat_id',
        'type',
        'task_type_id',
        'request_type',
        'priority',
        'status',
        'due_date',
        'estimated_hours',
        'actual_hours',
        'tags',
        'attachments',
        'story_points',
        'resolved_at',
        'closed_at',
        'archived_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'review_notes',
        'delegated_to',
        'delegated_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'attachments' => 'array',
        'due_date' => 'date',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'archived_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'delegated_at' => 'datetime',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function timeline(): BelongsTo
    {
        return $this->belongsTo(ProjectTimeline::class, 'timeline_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function qaAssignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_assigned_to');
    }

    /**
     * Penanggung jawab / delegasi tugas — a ticket can have more than one.
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function taskType(): BelongsTo
    {
        return $this->belongsTo(TaskType::class);
    }

    /**
     * A user "handles" a ticket when they are the pelaksana (assigned_to) OR
     * one of the delegated assignees (ticket_user pivot). Assignment in this
     * app is mostly done through delegation, so counting only assigned_to
     * under-reports per-user activity. Uses EXISTS so each ticket is counted
     * once even when the user is both pelaksana and delegate.
     */
    public function scopeHandledByUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('assigned_to', $userId)
                ->orWhereHas('assignees', fn (Builder $a) => $a->where('users.id', $userId));
        });
    }

    /**
     * Same as scopeHandledByUser but for a set of users (e.g. a team).
     */
    public function scopeHandledByAnyUser(Builder $query, array $userIds): Builder
    {
        return $query->where(function (Builder $q) use ($userIds) {
            $q->whereIn('assigned_to', $userIds)
                ->orWhereHas('assignees', fn (Builder $a) => $a->whereIn('users.id', $userIds));
        });
    }

    /**
     * Tickets that have no pelaksana and no delegated assignee at all.
     */
    public function scopeWithoutAnyAssignee(Builder $query): Builder
    {
        return $query->whereNull('assigned_to')
            ->whereDoesntHave('assignees');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function delegatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    /**
     * Get the active timer for the current user for this ticket.
     */
    public function activeTimeLog(): HasMany
    {
        return $this->hasMany(TimeLog::class)->where('user_id', auth()->id())->whereNull('end_time');
    }

    /**
     * Calculate total actual hours from time logs.
     */
    public function getTotalActualHoursAttribute(): float
    {
        $seconds = $this->timeLogs()->sum('duration_seconds') ?? 0;
        return round($seconds / 3600, 2);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(TicketStatusLog::class);
    }

    public function requiresApproval(): bool
    {
        return $this->request_type === 'berbayar';
    }

    /**
     * Log a status change for this ticket.
     */
    public function logStatusChange(string $toStatus, ?string $fromStatus = null, ?int $userId = null): void
    {
        $this->statusLogs()->create([
            'user_id' => $userId ?? auth()->id(),
            'from_status' => $fromStatus ?? $this->status,
            'to_status' => $toStatus,
            'story_points' => $this->story_points ?? 0,
            'changed_at' => now(),
        ]);
    }
}
