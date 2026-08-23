<?php

namespace App\Services\Ai;

use App\Contracts\Ai\VisionProvider;
use App\Jobs\ProcessAiDocument;
use App\Models\AiDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AiDocumentService
{
    public const DOCUMENT_TYPES = [
        'unknown',
        'supplier_invoice',
        'purchase_request',
        'goods_receipt',
        'sales_order',
        'sales_invoice',
    ];

    public function __construct(
        private readonly VisionProvider $vision,
    ) {
    }

    public function upload(UploadedFile $file, string $documentType, ?User $uploader = null): AiDocument
    {
        if (! in_array($documentType, self::DOCUMENT_TYPES, true)) {
            throw new RuntimeException('Tipe dokumen AI tidak valid.');
        }

        $companyId = app(\App\Services\CurrentCompany::class)->id();
        if ($companyId === null) {
            throw new RuntimeException('Company context belum tersedia.');
        }

        $sha256 = hash_file('sha256', $file->getRealPath());
        $existing = AiDocument::where('company_id', $companyId)->where('sha256', $sha256)->first();

        if ($existing) {
            throw new RuntimeException("Dokumen yang sama sudah pernah diunggah (#{$existing->id}).");
        }

        $path = $file->store('ai-documents/' . $companyId, 'local');

        $document = AiDocument::create([
            'company_id' => $companyId,
            'uploaded_by' => $uploader?->id,
            'document_type' => $documentType,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
            'sha256' => $sha256,
            'status' => 'uploaded',
        ]);
        app(\App\Services\AuditTrailService::class)->record($document, 'document_uploaded', null, $document->getAttributes());

        return $document;
    }

    public function queue(AiDocument $document): AiDocument
    {
        if (! in_array($document->status, ['uploaded', 'failed'], true)) {
            throw new RuntimeException('Dokumen ini sedang diproses atau sudah selesai direview.');
        }

        $document->update(['status' => 'processing', 'error_message' => null]);
        ProcessAiDocument::dispatch($document->id, (int) $document->company_id);

        return $document->fresh();
    }

    public function process(AiDocument $document): AiDocument
    {
        $disk = Storage::disk('local');
        $absolutePath = $disk->path($document->storage_path);

        if (! $disk->exists($document->storage_path)) {
            throw new RuntimeException('File dokumen OCR tidak ditemukan di private storage.');
        }

        $raw = $this->vision->extractStructured(
            $absolutePath,
            $document->mime_type,
            $this->schema($document->document_type),
            $this->instruction($document->document_type),
        );

        $normalized = $this->normalize($raw, $document->document_type);

        $document->update([
            'status' => 'review',
            'provider' => $this->vision->name(),
            'model' => $this->vision->model(),
            'extracted_payload' => $raw,
            'normalized_payload' => $normalized,
            'confidence_payload' => $raw['confidence'] ?? [],
            'error_message' => null,
        ]);
        app(\App\Services\AuditTrailService::class)->record($document, 'document_processed', null, ['status' => 'review', 'provider' => $document->provider, 'model' => $document->model]);

        return $document;
    }

    /** @return array<string, mixed> */
    public function schema(string $documentType): array
    {
        return [
            'document_type' => $documentType,
            'supplier_code' => 'string|null',
            'customer_code' => 'string|null',
            'document_number' => 'string|null',
            'document_date' => 'YYYY-MM-DD|null',
            'due_date' => 'YYYY-MM-DD|null',
            'currency_code' => 'ISO-4217 code|null',
            'warehouse_code' => 'string|null',
            'purchase_order_number' => 'string|null',
            'sales_order_number' => 'string|null',
            'lines' => [[
                'product_code' => 'string|null',
                'description' => 'string|null',
                'quantity' => 'decimal string',
                'unit_price' => 'decimal string',
                'tax_code' => 'string|null',
                'confidence' => 'number 0..1',
            ]],
            'subtotal' => 'decimal string|null',
            'tax_total' => 'decimal string|null',
            'total' => 'decimal string|null',
            'confidence' => 'object of field confidence values 0..1',
        ];
    }

    private function instruction(string $documentType): string
    {
        return match ($documentType) {
            'unknown' => 'Identifikasi tipe dokumen ini dan ekstrak data bisnis yang terlihat. Jangan mengarang kode master data.',
            'supplier_invoice' => 'Ekstrak invoice pemasok. Bedakan nomor invoice, tanggal invoice, jatuh tempo, supplier, item, kuantitas, harga, pajak, subtotal, dan total.',
            'purchase_request' => 'Ekstrak permintaan pembelian dan item yang diminta. Jangan mengisi supplier atau harga jika tidak terlihat.',
            'goods_receipt' => 'Ekstrak dokumen penerimaan barang, nomor PO, gudang, item, dan kuantitas yang diterima.',
            'sales_order' => 'Ekstrak pesanan penjualan, customer, item, kuantitas, harga, pajak, dan tanggal pengiriman bila terlihat.',
            'sales_invoice' => 'Ekstrak invoice penjualan, customer, nomor invoice, tanggal, jatuh tempo, item, pajak, dan total.',
            default => 'Ekstrak data bisnis yang terlihat tanpa menebak nilai yang tidak terbaca.',
        };
    }

    /** @param array<string, mixed> $raw @return array<string, mixed> */
    private function normalize(array $raw, string $documentType): array
    {
        $lines = is_array($raw['lines'] ?? null) ? $raw['lines'] : [];

        return [
            'document_type' => $documentType,
            'supplier_code' => $this->nullableString($raw['supplier_code'] ?? null),
            'customer_code' => $this->nullableString($raw['customer_code'] ?? null),
            'document_number' => $this->nullableString($raw['document_number'] ?? null),
            'document_date' => $this->nullableString($raw['document_date'] ?? null),
            'due_date' => $this->nullableString($raw['due_date'] ?? null),
            'currency_code' => strtoupper($this->nullableString($raw['currency_code'] ?? null) ?: 'IDR'),
            'warehouse_code' => $this->nullableString($raw['warehouse_code'] ?? null),
            'purchase_order_number' => $this->nullableString($raw['purchase_order_number'] ?? null),
            'sales_order_number' => $this->nullableString($raw['sales_order_number'] ?? null),
            'lines' => array_values(array_map(fn ($line) => [
                'product_code' => $this->nullableString(is_array($line) ? ($line['product_code'] ?? null) : null),
                'description' => $this->nullableString(is_array($line) ? ($line['description'] ?? null) : null),
                'quantity' => (string) (is_array($line) ? ($line['quantity'] ?? '0') : '0'),
                'unit_price' => (string) (is_array($line) ? ($line['unit_price'] ?? '0') : '0'),
                'tax_code' => $this->nullableString(is_array($line) ? ($line['tax_code'] ?? null) : null),
                'confidence' => (float) (is_array($line) ? ($line['confidence'] ?? 0) : 0),
            ], $lines)),
            'subtotal' => $this->nullableDecimal($raw['subtotal'] ?? null),
            'tax_total' => $this->nullableDecimal($raw['tax_total'] ?? null),
            'total' => $this->nullableDecimal($raw['total'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || strtolower($value) === 'null' ? null : $value;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        return $this->nullableString($value);
    }
}
