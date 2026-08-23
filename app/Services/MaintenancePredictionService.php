<?php

namespace App\Services;

use App\Models\MaintenanceEquipment;
use App\Models\MaintenancePrediction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MaintenancePredictionService
{
    public const RULE_VERSION = 'v1';

    public function predict(MaintenanceEquipment $equipment, ?Carbon $asOf = null): MaintenancePrediction
    {
        $asOf ??= now();
        $from = $asOf->copy()->subDays(90);
        $events = $equipment->workOrders()->whereBetween('created_at', [$from, $asOf])->get();
        $readings = $equipment->readings()->whereBetween('recorded_at', [$from, $asOf])->orderBy('recorded_at')->get();
        $schedules = $equipment->schedules()->where('is_active', true)->get();
        $corrective = $events->where('type', 'corrective');
        $completed = $equipment->workOrders()->where('status', 'completed')->latest('completed_at')->first();
        $contributors = [];
        $score = 0;

        $criticalityWeight = ['low' => 5, 'medium' => 10, 'high' => 15, 'critical' => 20][$equipment->criticality] ?? 10;
        $score += $criticalityWeight;
        $contributors[] = ['key' => 'criticality', 'weight' => $criticalityWeight, 'label' => 'Criticality equipment'];

        if ($corrective->count() > 0) {
            $weight = min(30, $corrective->count() * 10);
            $score += $weight;
            $contributors[] = ['key' => 'corrective_frequency', 'weight' => $weight, 'label' => 'Corrective maintenance dalam 90 hari'];
        }

        $downtime = (int) $events->sum('downtime_minutes');
        if ($downtime > 0) {
            $weight = min(20, (int) ceil($downtime / 120) * 5);
            $score += $weight;
            $contributors[] = ['key' => 'downtime', 'weight' => $weight, 'label' => 'Tren downtime meningkat'];
        }

        $overdue = $schedules->filter(fn ($schedule) => $schedule->next_due_at?->isPast())->count();
        if ($overdue > 0) {
            $score += 30;
            $contributors[] = ['key' => 'overdue_preventive', 'weight' => 30, 'label' => 'Preventive maintenance overdue'];
        }

        if ($completed?->completed_at && $completed->completed_at->lt($asOf->copy()->subDays(90))) {
            $score += 15;
            $contributors[] = ['key' => 'maintenance_age', 'weight' => 15, 'label' => 'Maintenance terakhir lebih dari 90 hari'];
        }

        if ($this->hasAnomaly($readings)) {
            $score += 15;
            $contributors[] = ['key' => 'reading_anomaly', 'weight' => 15, 'label' => 'Anomali reading temperature/vibration/load'];
        }

        $sufficient = $events->count() >= 1 && $readings->count() >= 2;
        $dataQuality = ['sufficient' => $sufficient, 'reading_count' => $readings->count(), 'maintenance_event_count' => $events->count()];
        $riskScore = $sufficient ? min(100, $score) : null;
        $level = $riskScore === null ? null : ($riskScore >= 80 ? 'critical' : ($riskScore >= 60 ? 'high' : ($riskScore >= 30 ? 'medium' : 'low')));
        $recommendation = $sufficient
            ? ($level === 'critical' || $level === 'high' ? 'Jadwalkan inspeksi dan preventive maintenance dalam 7 hari.' : 'Lanjutkan monitoring dan ikuti jadwal preventive maintenance.')
            : 'Tambahkan histori downtime atau meter reading sebelum prediksi dipakai.';

        $prediction = MaintenancePrediction::updateOrCreate(
            ['equipment_id' => $equipment->id, 'as_of' => $asOf->toDateString(), 'rule_version' => self::RULE_VERSION],
            [
                'risk_score' => $riskScore,
                'risk_level' => $level,
                'status' => $sufficient ? 'ready' : 'insufficient_data',
                'contributors' => $contributors,
                'recommendation' => $recommendation,
                'data_quality' => $dataQuality,
                'explanation_provider' => null,
            ],
        );
        app(AuditTrailService::class)->record($prediction, 'prediction_generated', null, $prediction->getAttributes());

        return $prediction;
    }

    private function hasAnomaly(Collection $readings): bool
    {
        return $readings->contains(fn ($reading) => (float) $reading->temperature >= 85 || (float) $reading->vibration >= 8 || (float) $reading->load_percent >= 95);
    }
}
