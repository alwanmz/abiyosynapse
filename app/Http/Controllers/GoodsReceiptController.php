<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class GoodsReceiptController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('purchasing/goods-receipts/page', [
            'receipts' => GoodsReceipt::with(['purchaseOrder:id,number,supplier_id', 'purchaseOrder.supplier:id,name', 'warehouse:id,code,name'])
                ->orderByDesc('created_at')
                ->get(),
            'receivableOrders' => PurchaseOrder::whereIn('status', ['approved', 'sent', 'partial'])
                ->with(['supplier:id,name', 'lines.product:id,code,name'])
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder, GoodsReceiptService $service): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.purchase_order_line_id' => 'required|exists:purchase_order_lines,id',
            'lines.*.quantity_received' => 'required|numeric|min:0.0001',
        ]);

        try {
            $service->receive($purchaseOrder, $validated['lines'], $request->user());
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.goods_receipt.created'));
    }

    public function show(GoodsReceipt $goodsReceipt): Response
    {
        return Inertia::render('purchasing/goods-receipts/show', [
            'receipt' => $goodsReceipt->load([
                'purchaseOrder:id,number,supplier_id',
                'purchaseOrder.supplier:id,name',
                'warehouse:id,code,name',
                'lines.product:id,code,name',
                'lines.inspections' => fn ($query) => $query->latest(),
            ]),
        ]);
    }

    public function putAway(Request $request, GoodsReceipt $goodsReceipt, GoodsReceiptService $service): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.goods_receipt_line_id' => 'required|exists:goods_receipt_lines,id',
            'lines.*.quantity_accepted' => 'required|numeric|min:0',
            'lines.*.notes' => 'nullable|string',
        ]);

        try {
            $service->inspectAndPutAway($goodsReceipt, $validated['lines']);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('messages.goods_receipt.put_away'));
    }
}
