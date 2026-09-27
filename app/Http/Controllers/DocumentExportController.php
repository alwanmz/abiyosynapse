<?php

namespace App\Http\Controllers;

use App\Services\Documents\DocumentExcelExportService;
use Symfony\Component\HttpFoundation\Response;

class DocumentExportController extends Controller
{
    public function xlsx(string $type, int $document, DocumentExcelExportService $export): Response
    {
        return $export->export($type, $document);
    }
}
