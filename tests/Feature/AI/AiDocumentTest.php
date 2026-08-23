<?php

use App\Contracts\Ai\VisionProvider;
use App\Models\AiActionRun;
use App\Models\AiDocument;
use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Ai\AiDocumentService;
use App\Services\Ai\OcrDraftService;
use App\Services\CurrentCompany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FakeVisionProviderForTest implements VisionProvider
{
    public function extractStructured(string $filePath, string $mimeType, array $schema, string $instruction): array
    {
        return ['document_type' => 'purchase_request', 'warehouse_code' => 'WH-OCR', 'lines' => [['product_code' => 'OCR-ITEM', 'quantity' => '3', 'unit_price' => '0', 'confidence' => 0.95]], 'confidence' => ['warehouse_code' => 0.95]];
    }
    public function name(): string { return 'fake'; }
    public function model(): string { return 'fake-model'; }
    public function isConfigured(): bool { return true; }
}

beforeEach(function () {
    $this->company = Company::factory()->create(['currency' => 'IDR']);
    app(CurrentCompany::class)->set($this->company);
    $this->user = User::factory()->create();
    Storage::fake('local');
    $this->app->bind(VisionProvider::class, fn () => new FakeVisionProviderForTest());
});

test('AI document is stored privately and duplicate upload is rejected', function () {
    $file = UploadedFile::fake()->image('invoice.png');
    $service = app(AiDocumentService::class);
    $document = $service->upload($file, 'unknown', $this->user);

    expect($document->status)->toBe('uploaded')
        ->and(Storage::disk('local')->exists($document->storage_path))->toBeTrue();

    expect(fn () => $service->upload($file, 'unknown', $this->user))
        ->toThrow(RuntimeException::class);
});

test('fake vision processing produces an editable review payload', function () {
    $document = app(AiDocumentService::class)->upload(UploadedFile::fake()->image('request.png'), 'purchase_request', $this->user);
    $processed = app(AiDocumentService::class)->process($document);

    expect($processed->status)->toBe('review')
        ->and($processed->provider)->toBe('fake')
        ->and($processed->normalized_payload['lines'][0]['product_code'])->toBe('OCR-ITEM');
});

test('OCR acceptance creates a purchase request draft without posting', function () {
    Currency::firstOrCreate(['code' => 'IDR'], ['numeric_code' => 360, 'name' => 'Rupiah', 'symbol' => 'Rp', 'minor_unit' => 2, 'is_active' => true]);
    CompanyCurrency::create(['company_id' => $this->company->id, 'currency_code' => 'IDR', 'is_active' => true, 'is_base' => true]);
    $uom = UnitOfMeasure::factory()->for($this->company)->create(['code' => 'PCS-OCR']);
    $warehouse = Warehouse::factory()->for($this->company)->create(['code' => 'WH-OCR']);
    Product::factory()->for($this->company)->create(['code' => 'OCR-ITEM', 'base_uom_id' => $uom->id, 'default_warehouse_id' => $warehouse->id, 'standard_cost' => 10]);
    $document = AiDocument::create(['company_id' => $this->company->id, 'uploaded_by' => $this->user->id, 'document_type' => 'purchase_request', 'original_filename' => 'request.png', 'storage_path' => 'ai-documents/test/request.png', 'mime_type' => 'image/png', 'file_size' => 1, 'sha256' => hash('sha256', 'request'), 'status' => 'review']);

    $result = app(OcrDraftService::class)->createDraft($document, ['document_type' => 'purchase_request', 'warehouse_code' => 'WH-OCR', 'lines' => [['product_code' => 'OCR-ITEM', 'quantity' => '3', 'unit_price' => '0', 'tax_code' => null]]], $this->user);
    $request = PurchaseRequest::with('lines')->findOrFail($result['id']);

    expect($request->status)->toBe('draft')
        ->and((float) $request->lines->first()->quantity)->toBe(3.0)
        ->and($document->fresh()->status)->toBe('accepted')
        ->and(AiActionRun::where('tool', 'ocr_create_purchase_request')->where('status', 'executed')->exists())->toBeTrue();
});
