<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Models\MaintenanceEquipment;
use App\Models\MaintenancePrediction;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceWorkOrder;
use App\Models\MaintenanceReading;
use App\Models\User;
use App\Models\WorkCenter;
use App\Services\MaintenancePredictionService;
use App\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class MaintenanceController extends Controller
{
    public function equipment(MaintenancePredictionService $predictions): Response
    {
        $equipment = MaintenanceEquipment::with(['workCenter:id,code,name', 'fixedAsset:id,number,name'])->latest()->get();
        $risk = $equipment->mapWithKeys(fn (MaintenanceEquipment $item) => [$item->id => $predictions->predict($item)])->all();

        return Inertia::render('maintenance/equipment/page', [
            'equipment' => $equipment,
            'predictions' => $risk,
            'workCenters' => WorkCenter::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'fixedAssets' => FixedAsset::whereIn('status', ['draft', 'active'])->orderBy('number')->get(['id', 'number', 'name']),
        ]);
    }

    public function workOrders(): Response
    {
        $companyId = app(\App\Services\CurrentCompany::class)->id();

        return Inertia::render('maintenance/work-orders/page', [
            'workOrders' => MaintenanceWorkOrder::with(['equipment:id,code,name', 'assignee:id,name', 'creator:id,name'])->latest()->get(),
            'equipment' => MaintenanceEquipment::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'users' => User::whereHas('companies', fn ($query) => $query->where('companies.id', $companyId))
                ->orderBy('name')
                ->get(['users.id', 'users.name']),
        ]);
    }

    public function schedules(): Response
    {
        return Inertia::render('maintenance/schedules/page', [
            'schedules' => MaintenanceSchedule::with('equipment:id,code,name')->latest()->get(),
            'equipment' => MaintenanceEquipment::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function readings(): Response
    {
        return Inertia::render('maintenance/readings/page', [
            'readings' => MaintenanceReading::with('equipment:id,code,name')->latest('recorded_at')->get(),
            'equipment' => MaintenanceEquipment::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function storeEquipment(Request $request, MaintenanceService $service): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255'],
            'work_center_id' => ['nullable', 'integer'], 'fixed_asset_id' => ['nullable', 'integer'],
            'manufacturer' => ['nullable', 'string', 'max:255'], 'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'], 'criticality' => ['required', 'in:low,medium,high,critical'],
            'commissioned_at' => ['nullable', 'date'], 'is_active' => ['nullable', 'boolean'],
        ]);
        if (! empty($validated['work_center_id'])) WorkCenter::findOrFail($validated['work_center_id']);
        if (! empty($validated['fixed_asset_id'])) FixedAsset::findOrFail($validated['fixed_asset_id']);
        $service->createEquipment($validated);

        return back()->with('success', 'Equipment maintenance berhasil dibuat.');
    }

    public function storeWorkOrder(Request $request, MaintenanceService $service): RedirectResponse
    {
        $validated = $request->validate([
            'equipment_id' => ['required', 'integer'], 'type' => ['required', 'in:preventive,corrective,inspection'],
            'priority' => ['required', 'integer', 'min:1', 'max:5'], 'scheduled_at' => ['nullable', 'date'],
            'symptom' => ['nullable', 'string'], 'assigned_to' => ['nullable', 'integer'], 'cost_base' => ['nullable', 'numeric', 'min:0'],
        ]);
        if (isset($validated['assigned_to'])) {
            abort_unless(
                User::whereHas('companies', fn ($query) => $query->where('companies.id', app(\App\Services\CurrentCompany::class)->id()))
                    ->whereKey($validated['assigned_to'])
                    ->exists(),
                422,
                'The selected assignee is not a member of the current company.',
            );
        }
        $service->createWorkOrder($validated, $request->user());

        return back()->with('success', 'Draft maintenance work order berhasil dibuat.');
    }

    public function storeSchedule(Request $request, MaintenanceService $service): RedirectResponse
    {
        $validated = $request->validate([
            'equipment_id' => ['required', 'integer'], 'interval_days' => ['nullable', 'integer', 'min:1'],
            'interval_runtime_minutes' => ['nullable', 'integer', 'min:1'], 'next_due_at' => ['required', 'date'],
            'checklist' => ['nullable', 'array'], 'is_active' => ['nullable', 'boolean'],
        ]);
        if (empty($validated['interval_days']) && empty($validated['interval_runtime_minutes'])) {
            return back()->with('error', 'Isi interval hari atau interval runtime.');
        }
        $service->createSchedule($validated);

        return back()->with('success', 'Jadwal preventive maintenance berhasil dibuat.');
    }

    public function storeReading(Request $request, MaintenanceService $service): RedirectResponse
    {
        $validated = $request->validate([
            'equipment_id' => ['required', 'integer'], 'recorded_at' => ['required', 'date'],
            'runtime_minutes' => ['nullable', 'integer', 'min:0'], 'temperature' => ['nullable', 'numeric'],
            'vibration' => ['nullable', 'numeric'], 'load_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'source' => ['required', 'in:manual,iot'], 'external_id' => ['nullable', 'string', 'max:255'], 'payload' => ['nullable', 'array'],
        ]);
        $service->recordReading($validated);

        return back()->with('success', 'Reading maintenance berhasil dicatat.');
    }

    public function open(MaintenanceWorkOrder $workOrder, MaintenanceService $service): RedirectResponse { return $this->transition(fn () => $service->open($workOrder)); }
    public function start(MaintenanceWorkOrder $workOrder, MaintenanceService $service): RedirectResponse { return $this->transition(fn () => $service->start($workOrder)); }

    public function complete(Request $request, MaintenanceWorkOrder $workOrder, MaintenanceService $service): RedirectResponse
    {
        $validated = $request->validate(['root_cause' => ['nullable', 'string'], 'action_taken' => ['required', 'string'], 'downtime_minutes' => ['nullable', 'integer', 'min:0'], 'cost_base' => ['nullable', 'numeric', 'min:0']]);
        return $this->transition(fn () => $service->complete($workOrder, $request->user(), $validated));
    }

    public function cancel(MaintenanceWorkOrder $workOrder, MaintenanceService $service): RedirectResponse { return $this->transition(fn () => $service->cancel($workOrder)); }

    public function predict(MaintenanceEquipment $equipment, MaintenancePredictionService $service): RedirectResponse
    {
        $service->predict($equipment);

        return back()->with('success', 'Risk maintenance diperbarui dari data histori aktual.');
    }

    private function transition(callable $callback): RedirectResponse
    {
        try { $callback(); } catch (RuntimeException $exception) { return back()->with('error', $exception->getMessage()); }
        return back()->with('success', 'Status work order berhasil diperbarui.');
    }
}
