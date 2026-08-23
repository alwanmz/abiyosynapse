<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class MaintenanceEquipment extends Model
{
    use HasFactory, BelongsToCompany, Auditable;

    protected $table = 'maintenance_equipment';

    protected $fillable = ['company_id', 'work_center_id', 'fixed_asset_id', 'code', 'name', 'manufacturer', 'model', 'serial_number', 'criticality', 'commissioned_at', 'is_active'];

    protected function casts(): array
    {
        return ['commissioned_at' => 'date', 'is_active' => 'boolean'];
    }

    public function workCenter(): BelongsTo { return $this->belongsTo(WorkCenter::class); }
    public function fixedAsset(): BelongsTo { return $this->belongsTo(FixedAsset::class); }
    public function workOrders(): HasMany { return $this->hasMany(MaintenanceWorkOrder::class, 'equipment_id'); }
    public function schedules(): HasMany { return $this->hasMany(MaintenanceSchedule::class, 'equipment_id'); }
    public function readings(): HasMany { return $this->hasMany(MaintenanceReading::class, 'equipment_id'); }
    public function predictions(): HasMany { return $this->hasMany(MaintenancePrediction::class, 'equipment_id'); }
}
