<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class MaintenanceWorkOrder extends Model
{
    use HasFactory, BelongsToCompany, Auditable;

    protected $table = 'maintenance_work_orders';
    protected $fillable = ['company_id', 'equipment_id', 'type', 'status', 'priority', 'scheduled_at', 'started_at', 'completed_at', 'symptom', 'root_cause', 'action_taken', 'downtime_minutes', 'cost_base', 'created_by', 'assigned_to', 'completed_by'];
    protected function casts(): array { return ['scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'cost_base' => 'decimal:6', 'priority' => 'integer', 'downtime_minutes' => 'integer']; }
    public function equipment(): BelongsTo { return $this->belongsTo(MaintenanceEquipment::class, 'equipment_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
}
