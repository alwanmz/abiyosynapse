<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DailyLogController extends Controller
{
    /** Total bytes of attachments allowed per log. */
    protected const ATTACHMENT_TOTAL_BYTES_LIMIT = 10 * 1024 * 1024; // 10 MB

    /** Maximum number of attachments per log. */
    protected const ATTACHMENT_MAX_COUNT = 5;

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $date = $request->get('date', now()->toDateString());
        $canViewTeamLogs = $user->isAdmin() || $user->hasPermissionTo('manage-daily-logs') || $user->hasPermissionTo('daily-logs.manage');
        $requestedScope = $request->get('scope', 'self');
        $scope = $canViewTeamLogs && $requestedScope === 'team' ? 'team' : 'self';

        $logs = DailyLog::with(['ticket:id,title,ticket_number', 'minute:id,title', 'attachments', 'user:id,name,avatar_path', 'reactions.user:id,name'])
            ->whereDate('log_date', $date)
            ->when(
                $scope === 'team',
                fn ($query) => $query->where('user_id', '!=', $user->id),
                fn ($query) => $query->where('user_id', $user->id),
            )
            ->latest()
            ->get();

        return Inertia::render('daily-logs/page', [
            'logs' => $logs,
            'selectedDate' => $date,
            'selectedScope' => $scope,
            'canViewTeamLogs' => $canViewTeamLogs,
            'moods' => collect(config('daily_log_moods', []))
                ->map(fn ($v, $k) => ['value' => $k] + $v)
                ->values(),
        ]);
    }

    public function toggleReaction(Request $request, DailyLog $dailyLog)
    {
        $validated = $request->validate([
            'emoji' => 'required|string|max:32',
        ]);

        $userId = Auth::id();
        $emoji = $validated['emoji'];

        $existing = $dailyLog->reactions()
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            $dailyLog->reactions()->create([
                'user_id' => $userId,
                'emoji' => $emoji,
            ]);
            $action = 'added';
        }

        try {
            \App\Events\DailyLogReactionToggled::dispatch($dailyLog, $emoji, $userId, $action);
        } catch (\Throwable $e) {
            // Best effort broadcast
        }

        if ($request->wantsJson()) {
            $dailyLog->load('reactions.user:id,name');
            return response()->json([
                'success' => true,
                'action' => $action,
                'reactions' => $dailyLog->reactions,
            ]);
        }

        return redirect()->back();
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DailyLog::class);

        $moodKeys = array_keys(config('daily_log_moods', []));

        $validated = $request->validate([
            'category' => 'required|string|in:development,meeting,support,documentation,research,deployment,other',
            'description' => 'required|string|min:5',
            'log_date' => 'required|date|before_or_equal:today',
            'mood' => ['nullable', 'string', Rule::in($moodKeys)],
            'energy_level' => 'nullable|integer|min:1|max:10',
            'duration_minutes' => 'nullable|integer|min:1|max:1440',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:32',
            'attachments' => 'nullable|array|max:' . self::ATTACHMENT_MAX_COUNT,
            'attachments.*' => 'file|mimes:jpg,jpeg,png,gif,webp,pdf|max:10240',
        ]);

        // Enforce the cumulative size limit (validator only checks per-file).
        if (! empty($validated['attachments'] ?? [])) {
            $totalBytes = collect($request->file('attachments'))->sum->getSize();
            if ($totalBytes > self::ATTACHMENT_TOTAL_BYTES_LIMIT) {
                return back()
                    ->withErrors(['attachments' => 'Total ukuran lampiran melebihi 10 MB.'])
                    ->withInput();
            }
        }

        $log = DB::transaction(function () use ($request, $validated) {
            $log = DailyLog::create([
                'user_id' => Auth::id(),
                'log_date' => $validated['log_date'],
                'log_number' => DailyLog::nextLogNumber($validated['log_date']),
                'category' => $validated['category'],
                'mood' => $validated['mood'] ?? null,
                'energy_level' => $validated['energy_level'] ?? null,
                'duration_minutes' => $validated['duration_minutes'] ?? null,
                'tags' => array_values(array_filter($validated['tags'] ?? [])) ?: null,
                'description' => $validated['description'],
                'is_automated' => false,
            ]);

            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store("daily-logs/{$log->id}", 'public');
                $log->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $file->getSize(),
                ]);
            }

            return $log;
        });

        return redirect()
            ->back()
            ->with('success', "Log {$log->log_number} berhasil disimpan.");
    }

    public function destroy(Request $request, DailyLog $dailyLog): RedirectResponse
    {
        $this->authorize('delete', $dailyLog);

        $logNumber = $dailyLog->log_number;
        // Preserve the date filter so the user lands back on the same day's view.
        $date = $request->query('date') ?? $dailyLog->log_date?->toDateString() ?? now()->toDateString();

        DB::transaction(function () use ($dailyLog) {
            // Best-effort cleanup: a missing or unwritable file should not
            // block deleting the row. We log + continue.
            foreach ($dailyLog->attachments as $att) {
                try {
                    Storage::disk('public')->delete($att->path);
                } catch (\Throwable $e) {
                    Log::warning('Failed to delete daily log attachment', [
                        'path' => $att->path,
                        'msg' => $e->getMessage(),
                    ]);
                }
            }

            // Cascade-on-delete on the FK already removes the attachment rows.
            $dailyLog->delete();
        });

        $message = $logNumber ? "Log {$logNumber} dihapus." : 'Catatan harian dihapus.';

        $scope = $request->query('scope');

        return redirect()
            ->route('daily-logs', array_filter(['date' => $date, 'scope' => $scope]))
            ->with('success', $message);
    }
}
