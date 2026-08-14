<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Notifications\ClientCommentedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CommentController extends Controller
{
    /**
     * Store a client-authored comment on their own ticket.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless((int) $ticket->client_id === (int) Auth::guard('client')->id(), 404);

        $validated = $request->validate([
            'comment' => 'required|string|max:5000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx|max:10240',
        ]);

        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('ticket-comments', 'public');
                $attachmentPaths[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'disk' => 'public',
                    'url' => Storage::disk('public')->url($path),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ];
            }
        }

        $client = Auth::guard('client')->user();

        $ticket->comments()->create([
            'user_id' => null,
            'client_id' => $client->id,
            'comment' => $validated['comment'],
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'type' => 'comment',
            // A client can never create an internal note.
            'is_internal' => false,
        ]);

        $this->notifyStaff($ticket, $client->nama);

        return redirect()->back()->with('success', 'Komentar berhasil dikirim.');
    }

    public function toggleReaction(Request $request, \App\Models\TicketComment $comment): RedirectResponse
    {
        $client = Auth::guard('client')->user();
        abort_unless((int) $comment->ticket->client_id === (int) $client->id, 404);

        $validated = $request->validate([
            'emoji' => 'required|string|max:32',
        ]);

        $emoji = $validated['emoji'];

        $existing = $comment->reactions()
            ->where('client_id', $client->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            $comment->reactions()->create([
                'client_id' => $client->id,
                'emoji' => $emoji,
            ]);
        }

        return redirect()->back();
    }

    /**
     * Notify the staff attached to the ticket (reporter, assignee, delegates)
     * so the new client comment surfaces in their existing notification bell.
     */
    private function notifyStaff(Ticket $ticket, string $clientName): void
    {
        $ticket->loadMissing(['reporter', 'assignedUser', 'assignees']);

        $recipients = collect([$ticket->reporter, $ticket->assignedUser])
            ->merge($ticket->assignees)
            ->filter()
            ->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        $notification = new ClientCommentedNotification(
            ticketId: $ticket->id,
            ticketNumber: $ticket->ticket_number,
            ticketTitle: $ticket->title,
            clientName: $clientName,
        );

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }
    }
}
