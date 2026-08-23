<?php

namespace App\Http\Controllers;

use App\Models\AiDocument;
use App\Services\Ai\AiDocumentService;
use App\Services\Ai\OcrDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AiDocumentController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('ai/documents/page', [
            'documents' => AiDocument::with('uploader:id,name')
                ->latest()
                ->get(),
            'documentTypes' => AiDocumentService::DOCUMENT_TYPES,
        ]);
    }

    public function store(Request $request, AiDocumentService $service): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', Rule::in(AiDocumentService::DOCUMENT_TYPES)],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:20480'],
        ]);

        try {
            $document = $service->upload($request->file('file'), $validated['document_type'], $request->user());
            $service->queue($document);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('ai.documents.show', $document)->with('success', 'Dokumen berhasil diunggah dan sedang diproses.');
    }

    public function show(AiDocument $document): InertiaResponse
    {
        return Inertia::render('ai/documents/show', [
            'document' => $document->load('uploader:id,name'),
        ]);
    }

    public function file(AiDocument $document): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->storage_path), 404);

        return response()->file($disk->path($document->storage_path), [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . addslashes($document->original_filename) . '"',
        ]);
    }

    public function process(AiDocument $document, AiDocumentService $service): RedirectResponse
    {
        try {
            $service->queue($document);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Dokumen dimasukkan kembali ke antrean OCR.');
    }

    public function reject(AiDocument $document): RedirectResponse
    {
        if (! in_array($document->status, ['review', 'failed'], true)) {
            return back()->with('error', 'Dokumen ini belum dapat ditolak.');
        }

        $document->update(['status' => 'rejected']);
        app(\App\Services\AuditTrailService::class)->record($document, 'document_rejected', ['status' => 'review'], ['status' => 'rejected'], null, auth()->id());

        return back()->with('success', 'Dokumen OCR ditandai sebagai ditolak.');
    }

    public function accept(Request $request, AiDocument $document, OcrDraftService $drafts): RedirectResponse
    {
        $payload = $request->validate([
            'payload' => ['required', 'array'],
            'payload.lines' => ['required', 'array', 'min:1'],
        ])['payload'];

        try {
            $drafts->createDraft($document, $payload, $request->user());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('ai.documents.show', $document)
            ->with('success', 'Draft ERP berhasil dibuat dan belum diposting.');
    }
}
