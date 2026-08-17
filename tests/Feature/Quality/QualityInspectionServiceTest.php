<?php

use App\Models\Company;
use App\Models\NonConformanceReport;
use App\Models\Product;
use App\Models\QualityInspection;
use App\Models\StockLot;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\CurrentCompany;
use App\Services\Quality\QualityInspectionService;
use Database\Seeders\CompanySeeder;

beforeEach(function () {
    (new CompanySeeder())->run();
    $this->company = Company::where('code', 'default')->first();
    app(CurrentCompany::class)->set($this->company);

    $uom = UnitOfMeasure::factory()->for($this->company)->create();
    $this->warehouse = Warehouse::factory()->for($this->company)->create();
    $this->product = Product::factory()->for($this->company)->create(['base_uom_id' => $uom->id]);
    $this->lot = StockLot::factory()->for($this->company)->create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
    ]);
});

test('a passing inspection does not open an NCR', function () {
    $service = app(QualityInspectionService::class);

    $inspection = $service->inspect('incoming', $this->lot, $this->product, 100, 100);

    expect($inspection->result)->toBe('pass');
    expect((float) $inspection->quantity_failed)->toBe(0.0);
    expect(NonConformanceReport::count())->toBe(0);
});

test('a failing inspection automatically opens an NCR', function () {
    $service = app(QualityInspectionService::class);

    $inspection = $service->inspect('incoming', $this->lot, $this->product, 100, 90, 'Dimension out of tolerance');

    expect($inspection->result)->toBe('fail');
    expect((float) $inspection->quantity_failed)->toBe(10.0);

    $ncr = NonConformanceReport::where('quality_inspection_id', $inspection->id)->first();
    expect($ncr)->not->toBeNull();
    expect($ncr->status)->toBe('open');
});

test('quantity passed cannot exceed quantity inspected', function () {
    $service = app(QualityInspectionService::class);

    $service->inspect('incoming', $this->lot, $this->product, 100, 150);
})->throws(RuntimeException::class);

test('an NCR moves through disposition, corrective action, and close', function () {
    $service = app(QualityInspectionService::class);
    $inspection = $service->inspect('final', $this->lot, $this->product, 100, 97);
    $ncr = NonConformanceReport::where('quality_inspection_id', $inspection->id)->first();

    $service->disposition($ncr, 'rework', 'Repaint the affected units');
    expect($ncr->fresh()->status)->toBe('disposition');
    expect($ncr->fresh()->disposition)->toBe('rework');

    $service->applyCorrectiveAction($ncr->fresh(), 'Retrained painting operator on spray technique');
    expect($ncr->fresh()->status)->toBe('corrective_action');

    $service->close($ncr->fresh());
    expect($ncr->fresh()->status)->toBe('closed');
    expect($ncr->fresh()->closed_at)->not->toBeNull();
});

test('an NCR cannot be closed without a disposition', function () {
    $service = app(QualityInspectionService::class);
    $inspection = $service->inspect('final', $this->lot, $this->product, 100, 97);
    $ncr = NonConformanceReport::where('quality_inspection_id', $inspection->id)->first();

    $service->close($ncr);
})->throws(RuntimeException::class);

test('a corrective action cannot be recorded before disposition', function () {
    $service = app(QualityInspectionService::class);
    $inspection = $service->inspect('final', $this->lot, $this->product, 100, 97);
    $ncr = NonConformanceReport::where('quality_inspection_id', $inspection->id)->first();

    $service->applyCorrectiveAction($ncr, 'premature action');
})->throws(RuntimeException::class);

test('NCR numbers are sequential per company', function () {
    $service = app(QualityInspectionService::class);

    $first = $service->inspect('incoming', $this->lot, $this->product, 100, 90);
    $second = $service->inspect('incoming', $this->lot, $this->product, 100, 90);

    $firstNcr = NonConformanceReport::where('quality_inspection_id', $first->id)->first();
    $secondNcr = NonConformanceReport::where('quality_inspection_id', $second->id)->first();

    expect($firstNcr->number)->not->toBe($secondNcr->number);
});
