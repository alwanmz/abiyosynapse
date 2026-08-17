<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PurchaseRequestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('purchasing/purchase-requests/page', [
            'requests' => PurchaseRequest::with(['warehouse:id,code,name', 'requester:id,name', 'lines.product:id,code,name'])
                ->orderByDesc('created_at')
                ->get(),
            'products' => Product::orderBy('code')->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|exists:products,id',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        $pr = PurchaseRequest::create([
            'number' => $this->nextNumber(),
            'warehouse_id' => $validated['warehouse_id'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'draft',
            'requested_by' => $request->user()->id,
        ]);

        foreach ($validated['lines'] as $line) {
            $pr->lines()->create($line);
        }

        return redirect()->back()->with('success', __('messages.purchase_request.created'));
    }

    public function submit(PurchaseRequest $purchaseRequest, PurchaseRequestService $service): RedirectResponse
    {
        try {
            $service->submit($purchaseRequest);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.purchase_request.submitted'));
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestService $service): RedirectResponse
    {
        try {
            $service->approve($purchaseRequest, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.purchase_request.approved'));
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestService $service): RedirectResponse
    {
        try {
            $service->reject($purchaseRequest, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.purchase_request.rejected'));
    }

    private function nextNumber(): string
    {
        $prefix = 'PR-' . now()->format('Y') . '-';

        $lastNumber = PurchaseRequest::where('number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($prefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $nextSequence = $lastNumber ? ((int) substr($lastNumber, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }
}
