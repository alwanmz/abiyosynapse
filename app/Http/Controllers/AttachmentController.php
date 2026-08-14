<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Serve a ticket attachment securely.
     */
    public function show(Request $request, Ticket $ticket, ?string $filename = null): StreamedResponse
    {
        $this->authorize('view', $ticket);

        $filename = $filename ?? $request->query('filename');
        if (! is_string($filename) || trim($filename) === '') {
            abort(404, 'File not found');
        }

        $filename = basename($filename);

        // Find the attachment in the ticket's attachments array
        $attachments = $ticket->attachments ?? [];
        $attachment = collect($attachments)->first(function ($item) use ($filename) {
            return isset($item['path']) && basename($item['path']) === $filename;
        });

        if (! $attachment || empty($attachment['path'])) {
            abort(404, 'File not found');
        }

        $path = $attachment['path'];
        $disk = $this->resolveDisk($attachment, $path);
        if ($disk === null) {
            abort(404, 'File not found');
        }

        $name = $attachment['name'] ?? $filename;
        $mimeType = $attachment['mime_type'] ?? Storage::disk($disk)->mimeType($path);

        return Storage::disk($disk)->response($path, $name, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . addcslashes($name, '"\\') . '"',
        ]);
    }

    private function resolveDisk(array $attachment, string $path): ?string
    {
        $candidates = array_values(array_unique(array_filter([
            $attachment['disk'] ?? null,
            'public',
            'local',
        ])));

        foreach ($candidates as $disk) {
            if (config("filesystems.disks.{$disk}") && Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }
}
