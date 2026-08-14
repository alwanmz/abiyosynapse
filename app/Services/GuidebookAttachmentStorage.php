<?php

namespace App\Services;

use App\Models\Guidebook;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Single place that stores guidebook files (the main PDF and the supporting
 * attachments) so the shape written to the DB stays consistent — mirrors
 * TicketAttachmentStorage.
 */
class GuidebookAttachmentStorage
{
    /**
     * Validation rule for the main PDF of a `pdf` guidebook.
     */
    public static function pdfValidationRule(): string
    {
        $mimes = implode(',', config('guidebook.pdf.allowed_mimes', ['pdf']));
        $maxKb = (int) config('guidebook.pdf.max_kb', 20 * 1024);

        return "file|mimes:{$mimes}|max:{$maxKb}";
    }

    /**
     * Validation rule for a single supporting attachment.
     */
    public static function attachmentValidationRule(): string
    {
        $mimes = implode(',', config('guidebook.attachments.allowed_mimes', ['jpg', 'jpeg', 'png', 'pdf']));
        $maxKb = (int) config('guidebook.attachments.max_kb', 10 * 1024);

        return "file|mimes:{$mimes}|max:{$maxKb}";
    }

    /**
     * Store the main PDF and return its storage path.
     */
    public static function storePdf(Guidebook $guidebook, UploadedFile $file): string
    {
        return $file->store('guidebooks/' . $guidebook->id, 'public');
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array<string, mixed>>
     */
    public static function storeAttachments(Guidebook $guidebook, array $files): array
    {
        $attachments = [];

        foreach ($files as $file) {
            $path = $file->store('guidebooks/' . $guidebook->id . '/attachments', 'public');
            $attachments[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'disk' => 'public',
                'url' => Storage::disk('public')->url($path),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ];
        }

        return $attachments;
    }

    /**
     * Best-effort delete; a missing file must never block the DB write.
     */
    public static function delete(?string $path, string $disk = 'public'): void
    {
        if (! $path) {
            return;
        }

        try {
            Storage::disk($disk)->delete($path);
        } catch (\Throwable $e) {
            Log::warning('Gagal menghapus berkas guidebook', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }
}
