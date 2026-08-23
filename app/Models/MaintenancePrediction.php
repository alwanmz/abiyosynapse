<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class MaintenancePrediction extends Model
{
    use HasFactory, BelongsToCompany, Auditable;
    protected $table = 'maintenance_predictions';
    protected $fillable = ['company_id', 'equipment_id', 'as_of', 'risk_score', 'risk_level', 'status', 'contributors', 'recommendation', 'data_quality', 'rule_version', 'explanation_provider'];
    protected function casts(): array { return ['as_of' => 'date', 'risk_score' => 'integer', 'contributors' => 'array', 'data_quality' => 'array']; }
    public function equipment(): BelongsTo { return $this->belongsTo(MaintenanceEquipment::class, 'equipment_id'); }
}
