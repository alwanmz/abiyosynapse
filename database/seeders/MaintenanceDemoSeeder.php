<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\MaintenanceEquipment;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceWorkOrder;
use App\Models\User;
use App\Models\WorkCenter;
use App\Services\CurrentCompany;
use Illuminate\Database\Seeder;

class MaintenanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'default')->first();
        $user = User::where('email', 'admin@abiyosynapse.local')->first();
        if (! $company || ! $user) return;
        app(CurrentCompany::class)->set($company);

        $workCenter = WorkCenter::where('code', 'WC-CUT-01')->first();
        $asset = FixedAsset::where('name', 'Mesin Cutting CHR-X1 Demo')->first();
        $equipment = MaintenanceEquipment::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'EQ-CUT-01'],
            ['name' => 'Mesin Cutting CHR-X1', 'work_center_id' => $workCenter?->id, 'fixed_asset_id' => $asset?->id, 'manufacturer' => 'Nexumi Demo', 'model' => 'CUT-X1', 'serial_number' => 'DEMO-CUT-001', 'criticality' => 'high', 'commissioned_at' => '2026-01-01', 'is_active' => true],
        );

        MaintenanceSchedule::updateOrCreate(
            ['company_id' => $company->id, 'equipment_id' => $equipment->id],
            ['interval_days' => 30, 'next_due_at' => '2026-08-01 08:00:00', 'checklist' => ['blade', 'lubrication', 'safety guard'], 'is_active' => true],
        );

        MaintenanceWorkOrder::updateOrCreate(
            ['company_id' => $company->id, 'equipment_id' => $equipment->id, 'type' => 'corrective', 'completed_at' => '2026-07-12 16:00:00'],
            ['status' => 'completed', 'priority' => 2, 'symptom' => 'Hasil potong tidak konsisten', 'root_cause' => 'Blade aus', 'action_taken' => 'Blade diganti dan alignment dikalibrasi', 'downtime_minutes' => 120, 'cost_base' => 750000, 'created_by' => $user->id, 'completed_by' => $user->id],
        );

        MaintenanceWorkOrder::updateOrCreate(
            ['company_id' => $company->id, 'equipment_id' => $equipment->id, 'type' => 'inspection', 'status' => 'open'],
            ['priority' => 3, 'scheduled_at' => now()->addDays(2), 'symptom' => 'Inspection berkala', 'created_by' => $user->id],
        );
        // No synthetic IoT telemetry is seeded. Prediction correctly reports
        // insufficient_data until real/manual readings are recorded.
    }
}
