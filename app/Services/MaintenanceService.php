<?php

namespace App\Services;

use App\Models\MaintenanceEquipment;
use App\Models\MaintenanceReading;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceWorkOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MaintenanceService
{
    public function createEquipment(array $attributes): MaintenanceEquipment
    {
        $equipment = MaintenanceEquipment::create($attributes);
        app(AuditTrailService::class)->record($equipment, 'maintenance_created', null, $equipment->getAttributes());

        return $equipment;
    }

    public function createWorkOrder(array $attributes, User $user): MaintenanceWorkOrder
    {
        $equipment = MaintenanceEquipment::findOrFail($attributes['equipment_id']);

        $workOrder = MaintenanceWorkOrder::create([
            ...$attributes,
            'equipment_id' => $equipment->id,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        app(AuditTrailService::class)->record($workOrder, 'maintenance_created', null, $workOrder->getAttributes());

        return $workOrder;
    }

    public function open(MaintenanceWorkOrder $workOrder): MaintenanceWorkOrder
    {
        $this->requireStatus($workOrder, 'draft', 'open');
        $old = ['status' => $workOrder->status];
        $workOrder->update(['status' => 'open']);
        app(AuditTrailService::class)->record($workOrder, 'maintenance_opened', $old, ['status' => 'open']);

        return $workOrder->fresh();
    }

    public function start(MaintenanceWorkOrder $workOrder): MaintenanceWorkOrder
    {
        $this->requireStatus($workOrder, 'open', 'in_progress');
        $old = ['status' => $workOrder->status];
        $workOrder->update(['status' => 'in_progress', 'started_at' => now()]);
        app(AuditTrailService::class)->record($workOrder, 'maintenance_started', $old, ['status' => 'in_progress']);

        return $workOrder->fresh();
    }

    public function complete(MaintenanceWorkOrder $workOrder, User $user, array $attributes = []): MaintenanceWorkOrder
    {
        $this->requireStatus($workOrder, 'in_progress', 'completed');
        $old = ['status' => $workOrder->status];
        $workOrder->update([
            ...$attributes,
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);
        app(AuditTrailService::class)->record($workOrder, 'maintenance_completed', $old, ['status' => 'completed', ...$attributes], null, $user->id);

        return $workOrder->fresh();
    }

    public function cancel(MaintenanceWorkOrder $workOrder): MaintenanceWorkOrder
    {
        if (in_array($workOrder->status, ['completed', 'cancelled'], true)) {
            throw new RuntimeException('Work order yang sudah selesai atau dibatalkan tidak dapat dibatalkan lagi.');
        }
        $old = ['status' => $workOrder->status];
        $workOrder->update(['status' => 'cancelled']);
        app(AuditTrailService::class)->record($workOrder, 'maintenance_cancelled', $old, ['status' => 'cancelled']);

        return $workOrder->fresh();
    }

    public function createSchedule(array $attributes): MaintenanceSchedule
    {
        MaintenanceEquipment::findOrFail($attributes['equipment_id']);

        $schedule = MaintenanceSchedule::create($attributes);
        app(AuditTrailService::class)->record($schedule, 'maintenance_created', null, $schedule->getAttributes());

        return $schedule;
    }

    public function recordReading(array $attributes): MaintenanceReading
    {
        if (($attributes['source'] ?? 'manual') === 'iot' && empty($attributes['external_id'])) {
            throw new RuntimeException('Reading dari IoT wajib memiliki external_id.');
        }

        MaintenanceEquipment::findOrFail($attributes['equipment_id']);

        $reading = MaintenanceReading::create($attributes);
        app(AuditTrailService::class)->record($reading, 'maintenance_created', null, $reading->getAttributes());

        return $reading;
    }

    private function requireStatus(MaintenanceWorkOrder $workOrder, string $expected, string $next): void
    {
        if ($workOrder->status !== $expected) {
            throw new RuntimeException("Work order harus berstatus {$expected} sebelum berpindah ke {$next}.");
        }
    }
}
