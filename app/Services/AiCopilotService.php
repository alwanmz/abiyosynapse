<?php

namespace App\Services;

use App\Models\AiActionRun;
use App\Models\AiDocument;
use App\Models\Customer;
use App\Models\MaintenanceEquipment;
use App\Models\NonConformanceReport;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PurchaseRequest;
use App\Models\QualityInspection;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Services\Manufacturing\MrpService;
use App\Services\Sales\SalesOrderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AiCopilotService
{
    private const TOOLS = [
        'executive_insight',
        'inventory_risk',
        'mrp_shortage',
        'demand_forecast',
        'production_schedule',
        'quality_risk',
        'cash_collection_plan',
        'draft_purchase_request',
        'draft_sales_order',
        'draft_production_order',
        'maintenance_risk',
        'maintenance_due',
        'maintenance_history',
        'ocr_document_review',
        'draft_maintenance_work_order',
    ];

    private const WRITE_TOOLS = [
        'draft_purchase_request',
        'draft_sales_order',
        'draft_production_order',
        'draft_maintenance_work_order',
    ];

    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly AiService $ai,
        private readonly ExecutiveDashboardService $dashboard,
        private readonly MrpService $mrp,
        private readonly SalesOrderService $salesOrders,
        private readonly MaintenanceService $maintenance,
        private readonly MaintenancePredictionService $maintenancePredictions,
    ) {
    }

    /**
     * @return array<int, array{key: string, mode: string}>
     */
    public function availableTools(): array
    {
        return [
            ['key' => 'executive_insight', 'mode' => 'read'],
            ['key' => 'inventory_risk', 'mode' => 'read'],
            ['key' => 'mrp_shortage', 'mode' => 'read'],
            ['key' => 'demand_forecast', 'mode' => 'read'],
            ['key' => 'production_schedule', 'mode' => 'read'],
            ['key' => 'quality_risk', 'mode' => 'read'],
            ['key' => 'cash_collection_plan', 'mode' => 'read'],
            ['key' => 'draft_purchase_request', 'mode' => 'draft'],
            ['key' => 'draft_sales_order', 'mode' => 'draft'],
            ['key' => 'draft_production_order', 'mode' => 'draft'],
            ['key' => 'maintenance_risk', 'mode' => 'read'],
            ['key' => 'maintenance_due', 'mode' => 'read'],
            ['key' => 'maintenance_history', 'mode' => 'read'],
            ['key' => 'ocr_document_review', 'mode' => 'read'],
            ['key' => 'draft_maintenance_work_order', 'mode' => 'draft'],
        ];
    }

    /** @return array<string, mixed> */
    public function chat(string $instruction, int $userId): array
    {
        $instruction = trim($instruction);

        if ($instruction === '') {
            throw new RuntimeException('Tuliskan perintah atau pertanyaan untuk AI Copilot.');
        }

        $plan = $this->plan($instruction);

        if (! in_array($plan['tool'], self::TOOLS, true)) {
            return [
                'status' => 'answered',
                'reply' => $plan['reply'] ?? $this->helpText(),
                'tool' => null,
                'data' => null,
            ];
        }

        if (in_array($plan['tool'], self::WRITE_TOOLS, true)) {
            $run = AiActionRun::create([
                'user_id' => $userId,
                'tool' => $plan['tool'],
                'instruction' => $instruction,
                'status' => 'planned',
                'arguments' => $plan['arguments'] ?? [],
            ]);

            return [
                'status' => 'confirmation_required',
                'action_run_id' => $run->id,
                'tool' => $run->tool,
                'reply' => $plan['reply'] ?? 'Saya siapkan draft. Periksa detailnya sebelum dikonfirmasi.',
                'arguments' => $run->arguments,
                'data' => null,
            ];
        }

        $run = AiActionRun::create([
            'user_id' => $userId,
            'tool' => $plan['tool'],
            'instruction' => $instruction,
            'status' => 'planned',
            'arguments' => $plan['arguments'] ?? [],
        ]);

        try {
            $result = $this->executeReadTool($plan['tool'], $plan['arguments'] ?? []);
            $run->update([
                'status' => 'executed',
                'result' => $result,
                'executed_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        return [
            'status' => 'answered',
            'tool' => $plan['tool'],
            'action_run_id' => $run->id,
            'reply' => $result['reply'],
            'data' => $result['data'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    public function confirm(AiActionRun $run, int $userId): array
    {
        if ($run->user_id !== $userId) {
            throw new RuntimeException('Aksi AI ini bukan milik pengguna yang sedang login.');
        }

        if ($run->status !== 'planned') {
            throw new RuntimeException('Aksi AI ini sudah diproses atau tidak lagi menunggu konfirmasi.');
        }

        $run->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        try {
            $result = $this->executeWriteTool($run->tool, $run->arguments ?? [], $userId);
            $run->update([
                'status' => 'executed',
                'result' => $result,
                'executed_at' => now(),
            ]);

            return [
                'status' => 'executed',
                'tool' => $run->tool,
                'reply' => $result['reply'],
                'data' => $result['data'] ?? null,
            ];
        } catch (\Throwable $exception) {
            $run->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function reject(AiActionRun $run, int $userId): array
    {
        if ($run->user_id !== $userId) {
            throw new RuntimeException('Aksi AI ini bukan milik pengguna yang sedang login.');
        }

        if ($run->status !== 'planned') {
            throw new RuntimeException('Aksi AI ini sudah diproses atau tidak lagi menunggu konfirmasi.');
        }

        $run->update(['status' => 'rejected']);

        return [
            'status' => 'rejected',
            'tool' => $run->tool,
            'reply' => 'Draft AI dibatalkan dan tidak ada dokumen yang dibuat.',
            'data' => null,
        ];
    }

    /** @return array{tool: string, arguments: array<string, mixed>, reply: string} */
    private function plan(string $instruction): array
    {
        if ($this->ai->isConfigured()) {
            try {
                $planned = $this->ai->generateJson(
                    'Anda adalah router AI untuk Nexumi ERP. Pilih tepat satu tool dari daftar yang diberikan. Jangan pernah memilih aksi untuk approve, post jurnal, mengubah stok, menghapus data, atau mengirim dokumen. Aksi tulis hanya boleh membuat draft. Jawab JSON object saja dengan schema: {"tool": string, "arguments": object, "reply": string}. Tool valid: ' . implode(', ', self::TOOLS),
                    $instruction,
                    config('services.deepseek.model'),
                );

                if (isset($planned['tool']) && in_array($planned['tool'], self::TOOLS, true)) {
                    return [
                        'tool' => $planned['tool'],
                        'arguments' => is_array($planned['arguments'] ?? null) ? $planned['arguments'] : [],
                        'reply' => (string) ($planned['reply'] ?? 'Saya akan memproses permintaan ini.'),
                    ];
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $this->deterministicPlan($instruction);
    }

    /** @return array{tool: string, arguments: array<string, mixed>, reply: string} */
    private function deterministicPlan(string $instruction): array
    {
        $text = Str::lower($instruction);

        if (Str::contains($text, ['buat pr', 'buat purchase request', 'buat permintaan pembelian'])) {
            return ['tool' => 'draft_purchase_request', 'arguments' => $this->documentArguments($instruction), 'reply' => 'Saya akan membuat draft Purchase Request. Draft tidak akan dikirim atau disetujui otomatis.'];
        }

        if (Str::contains($text, ['buat so', 'buat sales order', 'buat pesanan penjualan'])) {
            return ['tool' => 'draft_sales_order', 'arguments' => $this->documentArguments($instruction), 'reply' => 'Saya akan membuat draft Sales Order. Customer, produk, kuantitas, dan gudang akan divalidasi sebelum draft dibuat.'];
        }

        if (Str::contains($text, ['buat mo', 'buat production order', 'buat production order', 'buat perintah produksi'])) {
            return ['tool' => 'draft_production_order', 'arguments' => $this->documentArguments($instruction), 'reply' => 'Saya akan membuat draft Production Order. Order tetap Planned dan tidak mengonsumsi stok.'];
        }

        if (Str::contains($text, ['buat draft maintenance', 'buat work order maintenance', 'jadwalkan maintenance', 'buat wo maintenance'])) {
            return ['tool' => 'draft_maintenance_work_order', 'arguments' => $this->documentArguments($instruction), 'reply' => 'Saya akan membuat draft maintenance work order. Status tetap Draft dan perlu ditinjau sebelum dibuka atau dijalankan.'];
        }

        if (Str::contains($text, ['mesin berisiko', 'risiko maintenance', 'maintenance risk', 'equipment risk'])) {
            return ['tool' => 'maintenance_risk', 'arguments' => $this->documentArguments($instruction), 'reply' => 'Saya menghitung risiko equipment dari histori maintenance, downtime, schedule, dan reading yang tersedia.'];
        }

        if (Str::contains($text, ['maintenance overdue', 'maintenance jatuh tempo', 'preventive overdue', 'jadwal maintenance'])) {
            return ['tool' => 'maintenance_due', 'arguments' => [], 'reply' => 'Saya memeriksa preventive maintenance yang sudah jatuh tempo.'];
        }

        if (Str::contains($text, ['histori maintenance', 'riwayat maintenance', 'maintenance history'])) {
            return ['tool' => 'maintenance_history', 'arguments' => $this->documentArguments($instruction), 'reply' => 'Saya mengambil histori work order dan reading equipment yang diminta.'];
        }

        if (Str::contains($text, ['review ocr', 'dokumen ocr', 'inbox dokumen', 'invoice ai'])) {
            return ['tool' => 'ocr_document_review', 'arguments' => [], 'reply' => 'Saya memeriksa dokumen OCR yang menunggu review user.'];
        }

        if (Str::contains($text, ['mrp', 'material requirement', 'kebutuhan material', 'shortage', 'kekurangan bahan'])) {
            return ['tool' => 'mrp_shortage', 'arguments' => $this->documentArguments($instruction), 'reply' => 'Saya menghitung kebutuhan material dan shortage berdasarkan BOM serta stok company aktif.'];
        }

        if (Str::contains($text, ['anomali stok', 'risiko stok', 'stok kritis', 'inventory risk'])) {
            return ['tool' => 'inventory_risk', 'arguments' => [], 'reply' => 'Saya memeriksa stok kosong, stok rendah, dan nilai inventory yang perlu perhatian.'];
        }

        if (Str::contains($text, ['forecast', 'prediksi demand', 'prediksi penjualan', 'ramalan'])) {
            return ['tool' => 'demand_forecast', 'arguments' => [], 'reply' => 'Saya menghitung baseline forecast dari histori Sales Order company aktif.'];
        }

        if (Str::contains($text, ['jadwal produksi', 'production schedule', 'produksi terlambat', 'order produksi'])) {
            return ['tool' => 'production_schedule', 'arguments' => [], 'reply' => 'Saya memeriksa beban dan keterlambatan Production Order yang masih terbuka.'];
        }

        if (Str::contains($text, ['quality', 'kualitas', 'ncr', 'reject', 'cacat'])) {
            return ['tool' => 'quality_risk', 'arguments' => [], 'reply' => 'Saya merangkum risiko kualitas, inspeksi gagal, dan NCR terbuka.'];
        }

        if (Str::contains($text, ['piutang', 'tagihan customer', 'collection', 'cash collection', 'jatuh tempo'])) {
            return ['tool' => 'cash_collection_plan', 'arguments' => [], 'reply' => 'Saya menyusun prioritas collection dari invoice customer yang outstanding dan overdue.'];
        }

        if (Str::contains($text, ['dashboard', 'insight', 'analisa', 'analisis', 'ringkasan bisnis', 'kinerja'])) {
            return ['tool' => 'executive_insight', 'arguments' => [], 'reply' => 'Saya menganalisis ringkasan keuangan dan operasional terbaru.'];
        }

        return ['tool' => 'help', 'arguments' => [], 'reply' => $this->helpText()];
    }

    /** @return array<string, mixed> */
    private function executeReadTool(string $tool, array $arguments): array
    {
        return match ($tool) {
            'executive_insight' => $this->executiveInsight(),
            'inventory_risk' => $this->inventoryRisk(),
            'mrp_shortage' => $this->mrpShortage($arguments),
            'demand_forecast' => $this->demandForecast(),
            'production_schedule' => $this->productionSchedule(),
            'quality_risk' => $this->qualityRisk(),
            'cash_collection_plan' => $this->cashCollectionPlan(),
            'maintenance_risk' => $this->maintenanceRisk($arguments),
            'maintenance_due' => $this->maintenanceDue(),
            'maintenance_history' => $this->maintenanceHistory($arguments),
            'ocr_document_review' => $this->ocrDocumentReview(),
            default => ['reply' => $this->helpText(), 'data' => null],
        };
    }

    /** @return array<string, mixed> */
    private function executeWriteTool(string $tool, array $arguments, int $userId): array
    {
        return match ($tool) {
            'draft_purchase_request' => $this->draftPurchaseRequest($arguments, $userId),
            'draft_sales_order' => $this->draftSalesOrder($arguments, $userId),
            'draft_production_order' => $this->draftProductionOrder($arguments, $userId),
            'draft_maintenance_work_order' => $this->draftMaintenanceWorkOrder($arguments, $userId),
            default => throw new RuntimeException('Tool AI tidak diizinkan untuk eksekusi.'),
        };
    }

    /** @return array<string, mixed> */
    private function executiveInsight(): array
    {
        $from = now()->startOfYear()->toDateString();
        $to = now()->toDateString();
        $snapshot = $this->dashboard->build($from, $to);
        $reply = 'Ringkasan YTD: pendapatan ' . $this->money($snapshot['financial']['revenue'])
            . ', laba kotor ' . $this->money($snapshot['financial']['gross_profit'])
            . ', inventory ' . $this->money($snapshot['operations']['inventory_value'])
            . ', yield produksi ' . $snapshot['operations']['yield_rate'] . '%, dan '
            . $snapshot['quality']['open_ncrs'] . ' NCR terbuka.';

        if ($this->ai->isConfigured()) {
            try {
                $reply = $this->ai->analyze('Berikan 3 insight dan 3 tindakan prioritas dari snapshot Executive Dashboard ini.', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return ['reply' => $reply, 'data' => $snapshot];
    }

    /** @return array<string, mixed> */
    private function inventoryRisk(): array
    {
        $rows = StockLevel::with(['product:id,code,name,standard_cost', 'warehouse:id,code,name'])
            ->get()
            ->map(function (StockLevel $level): array {
                $quantity = (float) $level->quantity_on_hand;
                $standardCost = (float) ($level->product->standard_cost ?? 0);
                $risk = $quantity <= 0 ? 'critical' : ($quantity < 10 ? 'low' : 'normal');

                return [
                    'product_code' => $level->product?->code,
                    'product_name' => $level->product?->name,
                    'warehouse' => $level->warehouse?->code,
                    'quantity' => $quantity,
                    'value' => round($level->totalValue(), 2),
                    'risk' => $risk,
                    'standard_cost' => $standardCost,
                ];
            })
            ->sortByDesc(fn (array $row): int => $row['risk'] === 'critical' ? 2 : ($row['risk'] === 'low' ? 1 : 0))
            ->values()
            ->all();

        $critical = count(array_filter($rows, fn (array $row): bool => $row['risk'] !== 'normal'));

        return [
            'reply' => $critical > 0 ? "Ditemukan {$critical} posisi stok yang perlu perhatian." : 'Tidak ada posisi stok kritis dari batas baseline saat ini.',
            'data' => ['rows' => $rows, 'attention_count' => $critical],
        ];
    }

    /** @return array<string, mixed> */
    private function mrpShortage(array $arguments): array
    {
        $product = $this->resolveProduct($arguments['product_code'] ?? null, true);
        $warehouse = $this->resolveWarehouse($arguments['warehouse_code'] ?? null, $product);
        $demand = (float) ($arguments['demand_quantity'] ?? $this->extractNumber((string) ($arguments['raw'] ?? '')) ?? 100);

        if ($demand <= 0) {
            throw new RuntimeException('Demand MRP harus lebih besar dari nol.');
        }

        $rows = $this->mrp->run($product, $demand, $warehouse);
        $shortage = array_values(array_filter($rows, fn (array $row): bool => (float) $row['shortage'] > 0.0001));

        return [
            'reply' => count($shortage) > 0
                ? 'MRP menemukan ' . count($shortage) . ' material shortage untuk ' . $product->code . '.'
                : 'MRP tidak menemukan shortage untuk ' . $product->code . '.',
            'data' => ['product' => ['code' => $product->code, 'name' => $product->name], 'demand_quantity' => $demand, 'warehouse' => $warehouse->code, 'rows' => $rows],
        ];
    }

    /** @return array<string, mixed> */
    private function demandForecast(): array
    {
        $from = now()->subMonths(5)->startOfMonth();
        $orders = SalesOrder::with('lines')
            ->whereIn('status', ['approved', 'partial', 'fulfilled', 'closed'])
            ->whereBetween('order_date', [$from->toDateString(), now()->toDateString()])
            ->get();

        $months = [];
        foreach ($orders as $order) {
            $key = $order->order_date->format('Y-m');
            $months[$key] = ($months[$key] ?? 0) + (float) $order->lines->sum('quantity');
        }

        $months = collect($months)->sortKeys();
        $average = $months->isEmpty() ? 0 : $months->avg();
        $nextMonth = now()->addMonth()->format('Y-m');

        return [
            'reply' => 'Baseline forecast bulan ' . $nextMonth . ': ' . $this->quantity((float) $average) . ' unit dari rata-rata enam bulan terakhir.',
            'data' => ['history' => $months->map(fn ($quantity, $month): array => ['month' => $month, 'quantity' => (float) $quantity])->values()->all(), 'forecast_month' => $nextMonth, 'forecast_quantity' => round((float) $average, 4)],
        ];
    }

    /** @return array<string, mixed> */
    private function productionSchedule(): array
    {
        $orders = ProductionOrder::with(['product:id,code,name', 'operations:id,production_order_id,status,planned_minutes,actual_minutes'])
            ->whereNotIn('status', ['completed', 'closed'])
            ->orderBy('due_date')
            ->get();

        $today = now()->startOfDay();
        $rows = $orders->map(fn (ProductionOrder $order): array => [
            'number' => $order->number,
            'product' => $order->product?->code,
            'status' => $order->status,
            'due_date' => $order->due_date?->toDateString(),
            'late' => $order->due_date?->lt($today),
            'planned_minutes' => (float) $order->operations->sum('planned_minutes'),
            'actual_minutes' => (float) $order->operations->sum('actual_minutes'),
        ])->all();

        $late = count(array_filter($rows, fn (array $row): bool => $row['late']));

        return ['reply' => $late > 0 ? "Ada {$late} Production Order yang melewati due date." : 'Tidak ada Production Order terbuka yang melewati due date.', 'data' => ['rows' => $rows, 'late_count' => $late]];
    }

    /** @return array<string, mixed> */
    private function qualityRisk(): array
    {
        $failed = QualityInspection::with('product:id,code,name')
            ->where('result', 'fail')
            ->orderByDesc('inspected_at')
            ->limit(10)
            ->get();
        $ncrs = NonConformanceReport::with('inspection.product:id,code,name')
            ->where('status', '!=', 'closed')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return [
            'reply' => $ncrs->count() > 0 ? 'Ada ' . $ncrs->count() . ' NCR terbuka yang perlu ditindaklanjuti.' : 'Tidak ada NCR terbuka pada company aktif.',
            'data' => [
                'failed_inspections' => $failed->map(fn (QualityInspection $row): array => ['product' => $row->product?->code, 'failed_quantity' => (float) $row->quantity_failed, 'inspected_at' => $row->inspected_at?->toDateString()])->all(),
                'open_ncrs' => $ncrs->map(fn (NonConformanceReport $row): array => ['number' => $row->number, 'status' => $row->status, 'product' => $row->inspection?->product?->code])->all(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function cashCollectionPlan(): array
    {
        $invoices = SalesInvoice::withSum('salesReturns', 'total')
            ->whereIn('status', ['posted', 'paid'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->get(['id', 'number', 'customer_id', 'due_date', 'total', 'paid_amount'])
            ->map(function (SalesInvoice $invoice): array {
                $outstanding = max(0, (float) $invoice->total - (float) $invoice->paid_amount - (float) ($invoice->sales_returns_sum_total ?? 0));

                return ['id' => $invoice->id, 'number' => $invoice->number, 'due_date' => $invoice->due_date?->toDateString(), 'outstanding' => round($outstanding, 2)];
            })
            ->filter(fn (array $row): bool => $row['outstanding'] > 0.01)
            ->sortByDesc('outstanding')
            ->values()
            ->all();

        return ['reply' => count($invoices) > 0 ? 'Prioritas collection berisi ' . count($invoices) . ' invoice overdue.' : 'Tidak ada invoice overdue yang outstanding.', 'data' => ['invoices' => $invoices]];
    }

    /** @return array<string, mixed> */
    private function draftPurchaseRequest(array $arguments, int $userId): array
    {
        $product = $this->resolveProduct($arguments['product_code'] ?? null, false);
        $warehouse = $this->resolveWarehouse($arguments['warehouse_code'] ?? null, $product);
        $quantity = $this->requiredQuantity($arguments);

        $request = DB::transaction(function () use ($product, $warehouse, $quantity, $userId): PurchaseRequest {
            $request = PurchaseRequest::create([
                'number' => $this->nextNumber(PurchaseRequest::class, 'PR-'),
                'warehouse_id' => $warehouse->id,
                'notes' => 'Draft dibuat oleh AI Copilot. Wajib ditinjau sebelum submit.',
                'status' => 'draft',
                'requested_by' => $userId,
            ]);
            $request->lines()->create(['product_id' => $product->id, 'quantity' => $quantity]);

            return $request->fresh('lines.product');
        });

        return ['reply' => "Draft Purchase Request {$request->number} berhasil dibuat. Silakan tinjau kuantitas dan submit secara manual.", 'data' => ['type' => 'purchase_request', 'id' => $request->id, 'number' => $request->number, 'status' => $request->status, 'product' => $product->code, 'quantity' => $quantity]];
    }

    /** @return array<string, mixed> */
    private function draftSalesOrder(array $arguments, int $userId): array
    {
        $product = $this->resolveProduct($arguments['product_code'] ?? null, false);
        $warehouse = $this->resolveWarehouse($arguments['warehouse_code'] ?? null, $product);
        $customer = $this->resolveCustomer($arguments['customer_code'] ?? null);
        $quantity = $this->requiredQuantity($arguments);
        $deliveryDate = ! empty($arguments['requested_delivery_date'])
            ? Carbon::parse((string) $arguments['requested_delivery_date'])->toDateString()
            : now()->addDays(7)->toDateString();

        $order = DB::transaction(function () use ($product, $warehouse, $customer, $quantity, $userId, $deliveryDate): SalesOrder {
            $order = SalesOrder::create([
                'number' => $this->nextNumber(SalesOrder::class, 'SO-'),
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->toDateString(),
                'requested_delivery_date' => $deliveryDate,
                'status' => 'draft',
                'created_by' => $userId,
            ]);
            $order->lines()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => (float) $product->selling_price, 'tax_code_id' => null]);
            $this->salesOrders->recalculateTotals($order);

            return $order->fresh('lines');
        });

        return ['reply' => "Draft Sales Order {$order->number} berhasil dibuat. Status tetap Draft dan belum dikirim untuk approval.", 'data' => ['type' => 'sales_order', 'id' => $order->id, 'number' => $order->number, 'status' => $order->status, 'product' => $product->code, 'customer' => $customer->code, 'quantity' => $quantity]];
    }

    /** @return array<string, mixed> */
    private function draftProductionOrder(array $arguments, int $userId): array
    {
        $product = $this->resolveProduct($arguments['product_code'] ?? null, true);
        $warehouse = $this->resolveWarehouse($arguments['warehouse_code'] ?? null, $product);
        $quantity = $this->requiredQuantity($arguments);
        $startDate = $arguments['start_date'] ?? now()->toDateString();
        $dueDate = $arguments['due_date'] ?? now()->addDays(7)->toDateString();

        if ($dueDate < $startDate) {
            throw new RuntimeException('Due date Production Order tidak boleh sebelum start date.');
        }

        $order = ProductionOrder::create([
            'number' => $this->nextNumber(ProductionOrder::class, 'MO-'),
            'product_id' => $product->id,
            'bom_id' => $product->bom_id,
            'routing_id' => $product->routing_id,
            'warehouse_id' => $warehouse->id,
            'planned_quantity' => $quantity,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'status' => 'planned',
            'created_by' => $userId,
        ]);

        return ['reply' => "Draft Production Order {$order->number} berhasil dibuat. Status Planned dan belum ada material yang dikonsumsi.", 'data' => ['type' => 'production_order', 'id' => $order->id, 'number' => $order->number, 'status' => $order->status, 'product' => $product->code, 'quantity' => $quantity]];
    }

    private function maintenanceRisk(array $arguments): array
    {
        $equipment = $this->resolveEquipment($arguments['equipment_code'] ?? null);
        $prediction = $this->maintenancePredictions->predict($equipment);

        return [
            'reply' => $prediction->status === 'ready'
                ? "Risk {$equipment->code}: {$prediction->risk_level} ({$prediction->risk_score}/100)."
                : "Risk {$equipment->code} belum dapat dihitung karena data belum cukup.",
            'data' => ['equipment' => ['code' => $equipment->code, 'name' => $equipment->name], 'prediction' => $prediction->toArray()],
        ];
    }

    private function maintenanceDue(): array
    {
        $rows = \App\Models\MaintenanceSchedule::with('equipment:id,code,name')
            ->where('is_active', true)->whereNotNull('next_due_at')->where('next_due_at', '<=', now())->get();

        return [
            'reply' => $rows->isEmpty() ? 'Tidak ada preventive maintenance overdue.' : 'Ada ' . $rows->count() . ' preventive maintenance overdue.',
            'data' => ['rows' => $rows->map(fn ($row) => ['equipment' => $row->equipment?->code, 'next_due_at' => $row->next_due_at?->toDateString()])->all()],
        ];
    }

    private function maintenanceHistory(array $arguments): array
    {
        $equipment = $this->resolveEquipment($arguments['equipment_code'] ?? null);
        $orders = $equipment->workOrders()->latest()->limit(20)->get();
        $readings = $equipment->readings()->latest('recorded_at')->limit(20)->get();

        return ['reply' => "Histori {$equipment->code}: {$orders->count()} work order dan {$readings->count()} reading terakhir.", 'data' => ['work_orders' => $orders->toArray(), 'readings' => $readings->toArray()]];
    }

    private function ocrDocumentReview(): array
    {
        $documents = AiDocument::whereIn('status', ['review', 'failed'])->latest()->limit(20)->get(['id', 'original_filename', 'document_type', 'status', 'error_message']);

        return ['reply' => $documents->isEmpty() ? 'Tidak ada dokumen OCR yang menunggu review.' : 'Ada ' . $documents->count() . ' dokumen OCR yang perlu direview manual.', 'data' => ['documents' => $documents->toArray()]];
    }

    private function draftMaintenanceWorkOrder(array $arguments, int $userId): array
    {
        $equipment = $this->resolveEquipment($arguments['equipment_code'] ?? null);
        $order = $this->maintenance->createWorkOrder([
            'equipment_id' => $equipment->id,
            'type' => in_array($arguments['maintenance_type'] ?? null, ['preventive', 'corrective', 'inspection'], true) ? $arguments['maintenance_type'] : 'corrective',
            'priority' => max(1, min(5, (int) ($arguments['priority'] ?? 3))),
            'scheduled_at' => $arguments['scheduled_at'] ?? now()->addDays(7),
            'symptom' => $arguments['raw'] ?? null,
        ], \App\Models\User::findOrFail($userId));

        return ['reply' => "Draft maintenance work order #{$order->id} untuk {$equipment->code} berhasil dibuat. Status tetap Draft.", 'data' => ['id' => $order->id, 'equipment' => $equipment->code, 'status' => $order->status]];
    }

    private function resolveEquipment(?string $code): MaintenanceEquipment
    {
        $equipment = $code
            ? MaintenanceEquipment::where('code', $code)->where('is_active', true)->first()
            : MaintenanceEquipment::where('is_active', true)->orderBy('code')->first();

        if (! $equipment) {
            throw new RuntimeException($code ? "Equipment {$code} tidak ditemukan atau tidak aktif." : 'Sebutkan kode equipment yang ingin diperiksa.');
        }

        return $equipment;
    }

    private function resolveProduct(?string $code, bool $manufactured): Product
    {
        $query = Product::query()->where('status', 'active');
        if ($manufactured) {
            $query->where('make_or_buy', 'make')->whereNotNull('bom_id')->whereNotNull('routing_id');
        }

        $product = $code ? $query->where('code', $code)->first() : $query->orderBy('code')->first();

        if (! $product) {
            throw new RuntimeException($code ? "Produk {$code} tidak ditemukan atau tidak aktif di company ini." : 'Sebutkan kode produk yang ingin diproses.');
        }

        return $product;
    }

    private function resolveWarehouse(?string $code, ?Product $product = null): Warehouse
    {
        $warehouse = $code ? Warehouse::where('code', $code)->where('is_active', true)->first() : null;
        $warehouse ??= $product?->defaultWarehouse()->where('is_active', true)->first();
        $warehouse ??= Warehouse::where('is_active', true)->orderBy('code')->first();

        if (! $warehouse) {
            throw new RuntimeException('Tidak ada gudang aktif pada company ini.');
        }

        return $warehouse;
    }

    private function resolveCustomer(?string $code): Customer
    {
        $customer = $code ? Customer::where('code', $code)->where('is_active', true)->first() : Customer::where('is_active', true)->orderBy('code')->first();

        if (! $customer) {
            throw new RuntimeException($code ? "Customer {$code} tidak ditemukan atau tidak aktif." : 'Sebutkan kode customer untuk membuat draft SO.');
        }

        return $customer;
    }

    /** @return array<string, mixed> */
    private function documentArguments(string $instruction): array
    {
        $arguments = ['raw' => $instruction];
        if (preg_match('/\b([A-Z]{2,}[A-Z0-9]*-[A-Z0-9-]+)\b/i', $instruction, $matches)) {
            $arguments['product_code'] = strtoupper($matches[1]);
        }

        if (preg_match('/\b(?:warehouse|gudang)\s*[:#-]?\s*([A-Z0-9_-]+)\b/i', $instruction, $matches)) {
            $arguments['warehouse_code'] = strtoupper($matches[1]);
        }

        if (preg_match('/\b(?:customer|pelanggan)\s*[:#-]?\s*([A-Z0-9_-]+)\b/i', $instruction, $matches)) {
            $arguments['customer_code'] = strtoupper($matches[1]);
        }

        if (preg_match('/\b(?:equipment|mesin|machine)\s*[:#-]?\s*([A-Z0-9_-]+)\b/i', $instruction, $matches)) {
            $arguments['equipment_code'] = strtoupper($matches[1]);
        }

        if (preg_match('/\b(?:qty|quantity|jumlah|sebanyak|demand)\s*[:#-]?\s*([0-9]+(?:[.,][0-9]+)?)\b/i', $instruction, $matches)) {
            $arguments['quantity'] = (float) str_replace(',', '.', $matches[1]);
            $arguments['demand_quantity'] = $arguments['quantity'];
        }

        return $arguments;
    }

    private function requiredQuantity(array $arguments): float
    {
        $quantity = (float) ($arguments['quantity'] ?? $arguments['demand_quantity'] ?? $this->extractNumber((string) ($arguments['raw'] ?? '')) ?? 0);

        if ($quantity <= 0) {
            throw new RuntimeException('Sebutkan kuantitas yang lebih besar dari nol.');
        }

        return $quantity;
    }

    private function extractNumber(string $text): ?float
    {
        if (! preg_match('/\b([0-9]+(?:[.,][0-9]+)?)\b/', $text, $matches)) {
            return null;
        }

        return (float) str_replace(',', '.', $matches[1]);
    }

    private function nextNumber(string $model, string $prefix): string
    {
        $companyId = $this->currentCompany->id();
        $yearPrefix = $prefix . now()->format('Y') . '-';
        $last = $model::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('number', 'like', $yearPrefix . '%')
            ->orderByRaw('CAST(SUBSTR(number, ' . (strlen($yearPrefix) + 1) . ') AS INTEGER) DESC')
            ->value('number');

        $next = $last ? ((int) substr($last, strlen($yearPrefix))) + 1 : 1;

        return $yearPrefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function money(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    private function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function helpText(): string
    {
        return 'Saya dapat menganalisis dashboard, stok, MRP, produksi, kualitas, collection, maintenance, review OCR, serta membuat draft PR, SO, MO, dan maintenance work order. Aksi draft selalu meminta konfirmasi.';
    }
}
