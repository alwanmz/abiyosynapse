<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Derives a concise single-line ticket title from a free-form description.
 * Shared by the Telegram intake bot and the client portal submit form so both
 * channels produce titles in the same shape.
 */
class TicketTitle
{
    public static function fromDescription(string $description, string $fallback = 'Tiket Laporan'): string
    {
        $cleanDesc = trim($description);
        if ($cleanDesc === '') {
            return $fallback;
        }

        // Split into lines and take non-empty lines
        $lines = array_values(array_filter(array_map('trim', explode("\n", $cleanDesc))));
        $firstLine = $lines[0] ?? $cleanDesc;

        // If the first line is very short (e.g. "Foto 1", "Bukti 1") and line 2 exists, combine them
        if (count($lines) > 1 && mb_strlen($firstLine) < 15) {
            $firstLine = $firstLine . ' - ' . $lines[1];
        }

        // Collapse whitespace and linebreaks into a single space
        $singleLineTitle = preg_replace('/\s+/', ' ', $firstLine);

        return Str::limit($singleLineTitle, 90);
    }
}
