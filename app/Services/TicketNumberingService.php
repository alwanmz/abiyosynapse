<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\Ticket;
use Illuminate\Support\Str;

/**
 * Shared ticket-numbering / initial-status logic, extracted verbatim from
 * TicketController so both the web ticket form and the Telegram intake bot
 * produce consistent, collision-free ticket numbers.
 */
class TicketNumberingService
{
    /**
     * Must be called inside a DB::transaction with the Client (and Project,
     * if any) already locked via lockForUpdate() by the caller — this method
     * itself only adds the lock on the Ticket lookup, matching the original
     * TicketController::nextTicketNumber() behavior exactly.
     */
    public function nextTicketNumber(Client $client, ?Project $project): string
    {
        // Prefix mengikuti kode client agar nomor tiket konsisten dengan modul
        // client (mis. client SMT -> SMT-0001). Project dipakai sebagai fallback
        // hanya bila client tidak punya kode.
        $source = $client->kode ?: $project?->kode_project ?: $project?->name ?: $client->nama;
        $prefix = Str::of($source)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->substr(0, 4)
            ->toString();

        if ($prefix === '') {
            $prefix = 'TKT';
        }

        $lastTicket = Ticket::where('ticket_number', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $sequence = 1;
        if ($lastTicket && preg_match('/-(\d+)$/', $lastTicket->ticket_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $ticketNumber = $prefix . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Ticket::where('ticket_number', $ticketNumber)->exists());

        return $ticketNumber;
    }

    public function initialStatusFor(array $validated): string
    {
        if (($validated['request_type'] ?? null) === 'berbayar') {
            return 'pending';
        }

        $status = $validated['status'] ?? 'todo';

        return in_array($status, ['pending', 'backlog'], true) ? 'todo' : $status;
    }
}
