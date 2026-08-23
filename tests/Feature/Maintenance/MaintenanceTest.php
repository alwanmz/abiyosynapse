<?php

use App\Models\Company;
use App\Models\MaintenanceEquipment;
use App\Models\MaintenanceReading;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceWorkOrder;
use App\Models\User;
use App\Services\CurrentCompany;
use App\Services\MaintenancePredictionService;
use App\Services\MaintenanceService;

beforeEach(function () {
    $this->company = Company::factory()->create();
    app(CurrentCompany::class)->set($this->company);
    $this->user = User::factory()->create();
    $this->equipment = MaintenanceEquipment::create(['code' => 'EQ-TEST-01', 'name' => 'Test Machine', 'criticality' => 'high', 'is_active' => true]);
});

test('maintenance work order follows draft to completed lifecycle', function () {
    $service = app(MaintenanceService::class);
    $order = $service->createWorkOrder(['equipment_id' => $this->equipment->id, 'type' => 'corrective', 'priority' => 2], $this->user);
    $service->open($order);
    $service->start($order->fresh());
    $completed = $service->complete($order->fresh(), $this->user, ['action_taken' => 'Replaced component', 'downtime_minutes' => 30]);

    expect($completed->status)->toBe('completed')
        ->and($completed->completed_by)->toBe($this->user->id)
        ->and((int) $completed->downtime_minutes)->toBe(30);
});

test('prediction reports insufficient data without telemetry fabrication', function () {
    MaintenanceSchedule::create(['equipment_id' => $this->equipment->id, 'interval_days' => 30, 'next_due_at' => now()->subDay(), 'is_active' => true]);
    $prediction = app(MaintenancePredictionService::class)->predict($this->equipment);

    expect($prediction->status)->toBe('insufficient_data')
        ->and($prediction->risk_score)->toBeNull()
        ->and($prediction->data_quality['reading_count'])->toBe(0);
});

test('prediction calculates a high risk score from real maintenance and readings', function () {
    $service = app(MaintenanceService::class);
    $order = $service->createWorkOrder(['equipment_id' => $this->equipment->id, 'type' => 'corrective', 'priority' => 1], $this->user);
    $service->open($order);
    $service->start($order->fresh());
    $service->complete($order->fresh(), $this->user, ['action_taken' => 'Repair', 'downtime_minutes' => 480]);
    MaintenanceReading::create(['equipment_id' => $this->equipment->id, 'recorded_at' => now()->subDays(2), 'temperature' => 90, 'vibration' => 9, 'load_percent' => 98, 'source' => 'manual']);
    MaintenanceReading::create(['equipment_id' => $this->equipment->id, 'recorded_at' => now()->subDay(), 'temperature' => 91, 'vibration' => 9, 'load_percent' => 98, 'source' => 'manual']);
    $prediction = app(MaintenancePredictionService::class)->predict($this->equipment);

    expect($prediction->status)->toBe('ready')
        ->and($prediction->risk_score)->toBeGreaterThanOrEqual(60)
        ->and($prediction->risk_level)->toBeIn(['high', 'critical']);
});
