<?php

namespace App\Http\Controllers;

use App\Services\AuditTrailService;
use App\Services\Documents\DocumentOutputService;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class DocumentPrintController extends Controller
{
    public function __invoke(
        string $type,
        int $document,
        DocumentOutputService $output,
        AuditTrailService $audit,
    ): Response {
        $snapshot = $output->snapshot($type, $document);
        $run = $audit->record($snapshot['document'], 'document_printed', null, [
            'document_type' => $type,
            'format' => 'pdf',
        ], null, auth()->id());

        $pdf = Pdf::loadView('documents.print', [
            'snapshot' => $snapshot,
            'run' => $run,
        ])->setPaper('a4', $snapshot['orientation']);

        $filename = $this->filename($snapshot, 'pdf');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /** @param array<string, mixed> $snapshot */
    private function filename(array $snapshot, string $extension): string
    {
        $number = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $snapshot['header']['number']) ?: $snapshot['type'];

        return 'nexumi-' . $snapshot['type'] . '-' . $number . '.' . $extension;
    }
}
