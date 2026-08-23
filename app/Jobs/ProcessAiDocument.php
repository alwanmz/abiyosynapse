<?php

namespace App\Jobs;

use App\Models\AiDocument;
use App\Models\Company;
use App\Services\Ai\AiDocumentService;
use App\Services\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessAiDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $documentId,
        public readonly int $companyId,
    ) {
    }

    public function handle(AiDocumentService $service, CurrentCompany $currentCompany): void
    {
        $company = Company::findOrFail($this->companyId);
        $currentCompany->set($company);

        $document = AiDocument::where('company_id', $this->companyId)->findOrFail($this->documentId);
        $service->process($document);
    }

    public function failed(Throwable $exception): void
    {
        $document = AiDocument::withoutGlobalScopes()->where('company_id', $this->companyId)->find($this->documentId);

        $document?->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
