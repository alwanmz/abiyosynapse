<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class MaintenanceReading extends Model
{
    use HasFactory, BelongsToCompany, Auditable;
    protected $table = 'maintenance_readings';
    protected $fillable = ['company_id', 'equipment_id', 'recorded_at', 'runtime_minutes', 'temperature', 'vibration', 'load_percent', 'source', 'external_id', 'payload'];
    protected function casts(): array { return ['recorded_at' => 'datetime', 'runtime_minutes' => 'integer', 'temperature' => 'decimal:4', 'vibration' => 'decimal:4', 'load_percent' => 'decimal:4', 'payload' => 'array']; }
    public function equipment(): BelongsTo { return $this->belongsTo(MaintenanceEquipment::class, 'equipment_id'); }
}
