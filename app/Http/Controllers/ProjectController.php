<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects.
     */
    public function index(): Response
    {
        $projects = Cache::remember('projects.index', 300, function () {
            return Project::with([
                'team:id,name,color',
                'team.users:id,name,avatar_path',
                'client:id,kode,nama',
                'projectManager:id,name,avatar_path',
                'creator:id,name,avatar_path'
            ])
                ->select(
                    'id',
                    'name',
                    'description',
                    'team_id',
                    'client_id',
                    'kode_project',
                    'no_kontrak',
                    'tgl_mulai_kontrak',
                    'tgl_selesai_kontrak',
                    'tgl_implementasi',
                    'tgl_selesai_implementasi',
                    'jenis_pekerjaan',
                    'marketing_internal',
                    'project_manager_id',
                    'status',
                    'start_date',
                    'end_date',
                    'created_by',
                    'created_at',
                    'file_path',
                    'file_name',
                    'image_path',
                    'image_name'
                )
                ->latest()
                ->limit(500)
                ->get()
                ->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'description' => $project->description,
                        'status' => $project->status,
                        'start_date' => $project->start_date?->format('Y-m-d'),
                        'end_date' => $project->end_date?->format('Y-m-d'),
                        'client_id' => $project->client_id,
                        'client' => $project->client ? [
                            'id' => $project->client->id,
                            'kode' => $project->client->kode,
                            'nama' => $project->client->nama,
                        ] : null,
                        'kode_project' => $project->kode_project,
                        'no_kontrak' => $project->no_kontrak,
                        'tgl_mulai_kontrak' => $project->tgl_mulai_kontrak?->format('Y-m-d'),
                        'tgl_selesai_kontrak' => $project->tgl_selesai_kontrak?->format('Y-m-d'),
                        'tgl_implementasi' => $project->tgl_implementasi?->format('Y-m-d'),
                        'tgl_selesai_implementasi' => $project->tgl_selesai_implementasi?->format('Y-m-d'),
                        'jenis_pekerjaan' => $project->jenis_pekerjaan,
                        'marketing_internal' => $project->marketing_internal,
                        'team' => [
                            'id' => $project->team->id,
                            'name' => $project->team->name,
                            'color' => $project->team->color,
                            'members_count' => $project->team->users->count(),
                            'members' => $project->team->users->take(6)->map(function ($user) {
                                return [
                                    'id' => $user->id,
                                    'name' => $user->name,
                                    'avatar_url' => $user->avatar_url,
                                ];
                            }),
                        ],
                        'project_manager' => [
                            'id' => $project->projectManager->id,
                            'name' => $project->projectManager->name,
                            'avatar_url' => $project->projectManager->avatar_url,
                        ],
                        'creator' => $project->creator?->name,
                        'created_at' => $project->created_at->format('d M Y'),
                        'file_path' => $project->file_path,
                        'file_name' => $project->file_name,
                        'image_path' => $project->image_path,
                        'image_name' => $project->image_name,
                    ];
                });
        });

        $teams = Team::with('projectManager:id,name,avatar_path')
            ->select('id', 'name', 'color', 'project_manager_id')
            ->orderBy('name')
            ->get();

        $allUsers = User::with('role:id,name,display_name')
            ->select('id', 'name', 'email', 'role_id', 'avatar_path')
            ->orderBy('name')
            ->get();

        $clients = Client::select('id', 'kode', 'nama')
            ->where('is_active', true)
            ->orderBy('nama')
            ->get();

        return Inertia::render('projects/page', [
            'projects' => $projects,
            'teams' => $teams,
            'allUsers' => $allUsers,
            'clients' => $clients,
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:projects,name',
            'description' => 'nullable|string|max:1000',
            'team_id' => 'required|exists:teams,id',
            'client_id' => 'nullable|exists:clients,id',
            'kode_project' => 'nullable|string|max:50',
            'no_kontrak' => 'nullable|string|max:100',
            'tgl_mulai_kontrak' => 'nullable|date',
            'tgl_selesai_kontrak' => 'nullable|date',
            'tgl_implementasi' => 'nullable|date',
            'tgl_selesai_implementasi' => 'nullable|date',
            'jenis_pekerjaan' => 'nullable|in:implementasi,maintenance',
            'marketing_internal' => 'nullable|string|max:255',
            'project_manager_id' => 'required|exists:users,id',
            'status' => 'required|in:planning,in_progress,on_hold,completed,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'file' => 'nullable|file|mimes:doc,docx,pdf|max:20480',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:10240',
        ]);

        $filePath = null;
        $fileName = null;
        $imagePath = null;
        $imageName = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('projects/files', $fileName, 'public');
        }

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('projects/images', $imageName, 'public');
        }

        Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'team_id' => $validated['team_id'],
            'client_id' => $validated['client_id'] ?? null,
            'kode_project' => $validated['kode_project'] ?? null,
            'no_kontrak' => $validated['no_kontrak'] ?? null,
            'tgl_mulai_kontrak' => $validated['tgl_mulai_kontrak'] ?? null,
            'tgl_selesai_kontrak' => $validated['tgl_selesai_kontrak'] ?? null,
            'tgl_implementasi' => $validated['tgl_implementasi'] ?? null,
            'tgl_selesai_implementasi' => $validated['tgl_selesai_implementasi'] ?? null,
            'jenis_pekerjaan' => $validated['jenis_pekerjaan'] ?? null,
            'marketing_internal' => $validated['marketing_internal'] ?? null,
            'project_manager_id' => $validated['project_manager_id'],
            'status' => $validated['status'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'created_by' => $request->user()->id,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'image_path' => $imagePath,
            'image_name' => $imageName,
        ]);

        Cache::forget('projects.index');
        Cache::forget('projects.for-timelines');

        return back()->with('success', 'Project created successfully!');
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:projects,name,' . $project->id,
            'description' => 'nullable|string|max:1000',
            'team_id' => 'required|exists:teams,id',
            'client_id' => 'nullable|exists:clients,id',
            'kode_project' => 'nullable|string|max:50',
            'no_kontrak' => 'nullable|string|max:100',
            'tgl_mulai_kontrak' => 'nullable|date',
            'tgl_selesai_kontrak' => 'nullable|date',
            'tgl_implementasi' => 'nullable|date',
            'tgl_selesai_implementasi' => 'nullable|date',
            'jenis_pekerjaan' => 'nullable|in:implementasi,maintenance',
            'marketing_internal' => 'nullable|string|max:255',
            'project_manager_id' => 'required|exists:users,id',
            'status' => 'required|in:planning,in_progress,on_hold,completed,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'file' => 'nullable|file|mimes:doc,docx,pdf|max:20480',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:10240',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'team_id' => $validated['team_id'],
            'client_id' => $validated['client_id'] ?? null,
            'kode_project' => $validated['kode_project'] ?? null,
            'no_kontrak' => $validated['no_kontrak'] ?? null,
            'tgl_mulai_kontrak' => $validated['tgl_mulai_kontrak'] ?? null,
            'tgl_selesai_kontrak' => $validated['tgl_selesai_kontrak'] ?? null,
            'tgl_implementasi' => $validated['tgl_implementasi'] ?? null,
            'tgl_selesai_implementasi' => $validated['tgl_selesai_implementasi'] ?? null,
            'jenis_pekerjaan' => $validated['jenis_pekerjaan'] ?? null,
            'marketing_internal' => $validated['marketing_internal'] ?? null,
            'project_manager_id' => $validated['project_manager_id'],
            'status' => $validated['status'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ];

        if ($request->hasFile('file')) {
            if ($project->file_path) {
                Storage::disk('public')->delete($project->file_path);
            }
            
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('projects/files', $fileName, 'public');
            
            $updateData['file_path'] = $filePath;
            $updateData['file_name'] = $fileName;
        }

        if ($request->hasFile('image')) {
            if ($project->image_path) {
                Storage::disk('public')->delete($project->image_path);
            }
            
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('projects/images', $imageName, 'public');
            
            $updateData['image_path'] = $imagePath;
            $updateData['image_name'] = $imageName;
        }

        $project->update($updateData);

        Cache::forget('projects.index');
        Cache::forget('projects.for-timelines');

        return back()->with('success', 'Project updated successfully!');
    }

    /**
     * Remove the specified project.
     *
     * Deletes any associated file/image best-effort — a missing or
     * unwritable storage path should not block removing the DB row.
     */
    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        foreach (['file_path', 'image_path'] as $col) {
            if ($project->{$col}) {
                try {
                    Storage::disk('public')->delete($project->{$col});
                } catch (\Throwable $e) {
                    Log::warning('Failed to delete project asset', [
                        'project_id' => $project->id,
                        'column' => $col,
                        'msg' => $e->getMessage(),
                    ]);
                }
            }
        }

        $project->tickets()->update(['project_id' => null, 'timeline_id' => null]);
        $project->delete();

        Cache::forget('projects.index');
        Cache::forget('projects.for-timelines');

        return back()->with('success', 'Proyek berhasil dihapus.');
    }
}
