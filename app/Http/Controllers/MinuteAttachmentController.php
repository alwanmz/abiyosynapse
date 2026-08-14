<?php

namespace App\Http\Controllers;

use App\Models\MinuteAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MinuteAttachmentController extends Controller
{
    public function destroy(MinuteAttachment $minuteAttachment): RedirectResponse
    {
        $minute = $minuteAttachment->minute;

        if (Auth::user()->cannot('update', $minute)) {
            abort(403);
        }

        $filename = $minuteAttachment->original_name;
        Storage::disk('public')->delete($minuteAttachment->path);
        $minuteAttachment->delete();

        $minute->logActivity('attachment_deleted', ['filename' => $filename]);

        return redirect()->back()->with('success', 'Lampiran berhasil dihapus.');
    }
}
