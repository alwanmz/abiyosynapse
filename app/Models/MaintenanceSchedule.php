<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class MaintenanceSchedule extends Model
{
    use HasFactory, BelongsToCompany, Auditable;
    protected $table = 'maintenance_schedules';
    protected $fillable = ['company_id', 'equipment_id', 'interval_days', 'interval_runtime_minutes', 'next_due_at', 'checklist', 'is_active'];
    protected function casts(): array { return ['next_due_at' => 'datetime', 'checklist' => 'array', 'is_active' => 'boolean']; }
    public function equipment(): BelongsTo { return $this->belongsTo(MaintenanceEquipment::class, 'equipment_id'); }
    public function isOverdue(): bool { return $this->is_active && $this->next_due_at?->isPast(); }
}
