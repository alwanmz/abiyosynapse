<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Single place that turns uploaded files into the `attachments` JSON shape
 * stored on tickets, so the staff app and the client portal stay identical.
 */
class TicketAttachmentStorage
{
    /**
     * Validation rule for a single uploaded attachment.
     */
    public static function validationRule(): string
    {
        $mimes = implode(',', config('tickets.attachments.allowed_mimes', ['jpg', 'jpeg', 'png', 'pdf']));
        $maxKb = (int) config('tickets.attachments.max_kb', 30 * 1024);

        return "file|mimes:{$mimes}|max:{$maxKb}";
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array<string, mixed>>
     */
    public static function store(Ticket $ticket, array $files): array
    {
        $attachments = [];

        foreach ($files as $file) {
            $path = $file->store('ticket-attachments/' . $ticket->id, 'public');
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
}
