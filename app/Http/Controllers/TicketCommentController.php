<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketCommentController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'comment' => 'required|string|max:5000',
            'attachments' => 'nullable|array',
            'attachments.*' => $this->attachmentValidationRule(),
            'type' => 'nullable|in:comment,status_change,assignment_change,system',
            'is_internal' => 'nullable|boolean',
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

        $comment = $ticket->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $validated['comment'],
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'type' => $validated['type'] ?? 'comment',
            'is_internal' => $validated['is_internal'] ?? false,
        ]);

        $comment->load('user:id,name,avatar_path');

        return redirect()->back()->with('success', 'Comment added successfully');
    }

    public function update(Request $request, TicketComment $comment)
    {
        if ($comment->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:5000',
            'is_internal' => 'nullable|boolean',
        ]);

        $comment->update([
            'comment' => $validated['comment'],
            'is_internal' => $validated['is_internal'] ?? $comment->is_internal,
        ]);

        return redirect()->back()->with('success', 'Comment updated successfully');
    }

    private function attachmentValidationRule(): string
    {
        $mimes = implode(',', config('tickets.attachments.allowed_mimes', ['jpg', 'jpeg', 'png', 'pdf']));
        $maxKb = (int) config('tickets.attachments.max_kb', 30 * 1024);

        return "file|mimes:{$mimes}|max:{$maxKb}";
    }

    public function destroy(TicketComment $comment)
    {
        if ($comment->user_id !== auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        if ($comment->attachments) {
            foreach ($comment->attachments as $attachment) {
                if (isset($attachment['path'])) {
                    Storage::disk($attachment['disk'] ?? 'public')->delete($attachment['path']);
                }
            }
        }

        $comment->delete();

        return redirect()->back()->with('success', 'Comment deleted successfully');
    }

    public function toggleReaction(Request $request, TicketComment $comment)
    {
        $validated = $request->validate([
            'emoji' => 'required|string|max:32',
        ]);

        $userId = auth()->id();
        $emoji = $validated['emoji'];

        $existing = $comment->reactions()
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            $comment->reactions()->create([
                'user_id' => $userId,
                'emoji' => $emoji,
            ]);
        }

        return redirect()->back();
    }
}
