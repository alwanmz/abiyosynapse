<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Routing;
use App\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoutingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('manufacturing/routings/page', [
            'routings' => Routing::with(['product:id,code,name', 'operations.workCenter:id,code,name'])
                ->orderByDesc('created_at')
                ->get(),
            'products' => Product::where('status', 'active')->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'workCenters' => WorkCenter::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $routing = Routing::create([
                'product_id' => $validated['product_id'],
                'code' => $validated['code'],
                'version' => $this->nextVersion($validated['product_id']),
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['operations'] as $index => $operation) {
                $routing->operations()->create([
                    'sequence' => ($index + 1) * 10,
                    'name' => $operation['name'],
                    'work_center_id' => $operation['work_center_id'],
                    'setup_minutes' => $operation['setup_minutes'] ?? 0,
                    'run_minutes_per_unit' => $operation['run_minutes_per_unit'],
                    'is_inspection_point' => $operation['is_inspection_point'] ?? false,
                ]);
            }
        });

        return redirect()->back()->with('success', __('messages.routing.created'));
    }

    public function update(Request $request, Routing $routing): RedirectResponse
    {
        $validated = $this->validated($request, $routing->id);

        DB::transaction(function () use ($validated, $routing) {
            $routing->update([
                'code' => $validated['code'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $routing->operations()->delete();
            foreach ($validated['operations'] as $index => $operation) {
                $routing->operations()->create([
                    'sequence' => ($index + 1) * 10,
                    'name' => $operation['name'],
                    'work_center_id' => $operation['work_center_id'],
                    'setup_minutes' => $operation['setup_minutes'] ?? 0,
                    'run_minutes_per_unit' => $operation['run_minutes_per_unit'],
                    'is_inspection_point' => $operation['is_inspection_point'] ?? false,
                ]);
            }
        });

        return redirect()->back()->with('success', __('messages.routing.updated'));
    }

    public function destroy(Routing $routing): RedirectResponse
    {
        if ($routing->product && $routing->product->routing_id === $routing->id) {
            return redirect()->back()->with('error', __('messages.routing.cannot_delete_in_use'));
        }

        $routing->delete();

        return redirect()->back()->with('success', __('messages.routing.deleted'));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'product_id' => ['required', $this->tenantExists('products')],
            'code' => ['required', 'string', 'max:50', $this->tenantUnique('routings', 'code', $ignoreId)],
            'status' => 'required|in:draft,approved,active,obsolete',
            'notes' => 'nullable|string',
            'operations' => 'required|array|min:1',
            'operations.*.name' => 'required|string|max:255',
            'operations.*.work_center_id' => ['required', $this->tenantExists('work_centers')],
            'operations.*.setup_minutes' => 'nullable|numeric|min:0',
            'operations.*.run_minutes_per_unit' => 'required|numeric|min:0',
            'operations.*.is_inspection_point' => 'boolean',
        ]);
    }

    private function nextVersion(int $productId): int
    {
        return Routing::where('product_id', $productId)->max('version') + 1;
    }
}
