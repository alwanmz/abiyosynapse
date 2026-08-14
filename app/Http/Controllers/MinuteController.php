<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\DailyLog;
use App\Models\Minute;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MinuteController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Minute::class);

        $projectId = $request->get('project_id');

        $minutes = Minute::with(['project:id,name', 'creator:id,name'])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->latest('meeting_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $projects = Project::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('minutes/page', [
            'minutes' => $minutes,
            'projects' => $projects,
            'filters' => ['project_id' => $projectId],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('minutes/create', [
            'projects' => Project::select('id', 'name')->orderBy('name')->get(),
            'users' => User::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Minute::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'meeting_date' => 'required|date',
            'location' => 'nullable|string|max:255',
            'project_id' => 'nullable|integer|exists:projects,id',
            'attendees' => 'nullable|array|max:50',
            'attendees.*.name' => 'required|string|max:100',
            'agenda' => 'nullable|string|max:5000',
            'raw_transcript' => 'nullable|string|max:20000',
            'summary' => 'nullable|string|max:10000',
            'decisions' => 'nullable|array|max:50',
            'decisions.*.text' => 'required|string|max:1000',
            'decisions.*.owner_name' => 'nullable|string|max:100',
            'decisions.*.due_date' => 'nullable|date_format:Y-m-d',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx|max:10240',
        ]);

        $minute = Minute::create(array_merge(
            collect($validated)->except('attachments')->toArray(),
            ['created_by' => Auth::id()]
        ));

        $minute->logActivity('created', ['title' => $minute->title]);

        DailyLog::create([
            'user_id'    => Auth::id(),
            'minute_id'  => $minute->id,
            'log_date'   => $minute->meeting_date->toDateString(),
            'log_number' => DailyLog::nextLogNumber($minute->meeting_date),
            'category'   => 'meeting',
            'description' => "Menulis notulensi: {$minute->title}",
            'is_automated' => true,
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store("minutes/{$minute->id}", 'public');
            $minute->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'path'          => $path,
                'mime_type'     => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes'    => $file->getSize(),
            ]);
            $minute->logActivity('attachment_added', ['filename' => $file->getClientOriginalName()]);
        }

        return redirect()->route('minutes.show', $minute)
            ->with('success', 'Notulensi berhasil disimpan.');
    }

    public function show(Minute $minute): Response
    {
        $this->authorize('view', $minute);

        $minute->load(['project:id,name', 'creator:id,name', 'attachments', 'activityLogs.user:id,name']);

        $projects = Project::with(['team:id,name', 'team.users:id,name,avatar_path', 'client:id,kode,nama'])
            ->select('id', 'name', 'status', 'team_id', 'client_id')
            ->orderBy('name')
            ->get();
        $clients = Client::select('id', 'kode', 'nama', 'deskripsi', 'is_active')
            ->where('is_active', true)
            ->orderBy('nama')
            ->get();
        $allUsers = User::with('role:id,name,display_name')
            ->select('id', 'name', 'role_id', 'avatar_path')
            ->orderBy('name')
            ->get();
        $timelines = ProjectTimeline::select('id', 'project_id', 'title', 'type', 'status')
            ->whereIn('status', ['pending', 'in_progress'])
            ->get();

        return Inertia::render('minutes/show', [
            'minute' => $minute,
            'projects' => $projects,
            'clients' => $clients,
            'timelines' => $timelines,
            'allUsers' => $allUsers,
            'canEdit' => Auth::user()->can('update', $minute),
        ]);
    }

    public function edit(Minute $minute): Response
    {
        $this->authorize('update', $minute);

        $minute->load(['project:id,name', 'creator:id,name', 'attachments']);

        return Inertia::render('minutes/edit', [
            'minute' => $minute,
            'projects' => Project::select('id', 'name')->orderBy('name')->get(),
            'users' => User::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Minute $minute): RedirectResponse
    {
        $this->authorize('update', $minute);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'meeting_date' => 'required|date',
            'location' => 'nullable|string|max:255',
            'project_id' => 'nullable|integer|exists:projects,id',
            'attendees' => 'nullable|array|max:50',
            'attendees.*.name' => 'required|string|max:100',
            'agenda' => 'nullable|string|max:5000',
            'raw_transcript' => 'nullable|string|max:20000',
            'summary' => 'nullable|string|max:10000',
            'decisions' => 'nullable|array|max:50',
            'decisions.*.text' => 'required|string|max:1000',
            'decisions.*.owner_name' => 'nullable|string|max:100',
            'decisions.*.due_date' => 'nullable|date_format:Y-m-d',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx|max:10240',
        ]);

        $trackableFields = ['title', 'meeting_date', 'location', 'agenda', 'summary', 'raw_transcript'];
        $changes = [];
        $data = collect($validated)->except('attachments')->toArray();
        foreach ($trackableFields as $field) {
            if (array_key_exists($field, $data) && (string) ($minute->$field ?? '') !== (string) ($data[$field] ?? '')) {
                $changes[$field] = ['from' => $minute->$field, 'to' => $data[$field]];
            }
        }

        $minute->update($data);

        if (!empty($changes)) {
            $minute->logActivity('updated', ['changed' => array_keys($changes)]);

            DailyLog::create([
                'user_id'     => Auth::id(),
                'minute_id'   => $minute->id,
                'log_date'    => now()->toDateString(),
                'log_number'  => DailyLog::nextLogNumber(now()),
                'category'    => 'meeting',
                'description' => "Memperbarui notulensi: {$minute->title}",
                'is_automated' => true,
            ]);
        }

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store("minutes/{$minute->id}", 'public');
            $minute->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'path'          => $path,
                'mime_type'     => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes'    => $file->getSize(),
            ]);
            $minute->logActivity('attachment_added', ['filename' => $file->getClientOriginalName()]);
        }

        return redirect()->route('minutes.show', $minute)
            ->with('success', 'Notulensi berhasil diperbarui.');
    }

    public function destroy(Minute $minute): RedirectResponse
    {
        $this->authorize('delete', $minute);

        foreach ($minute->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->path);
        }

        $minute->delete();

        return redirect()->route('minutes.index')
            ->with('success', 'Notulensi berhasil dihapus.');
    }
}
