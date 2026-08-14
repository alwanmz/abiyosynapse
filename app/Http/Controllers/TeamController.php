<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * Display a listing of teams.
     */
    public function index(): Response
    {
        $teams = Cache::remember('teams.index', 300, function () {
            return Team::with([
                'creator:id,name,avatar_path',
                'projectManager:id,name,avatar_path',
                'users:id,name,email,role_id,avatar_path',
                'users.role:id,name,display_name',
            ])
                ->select('id', 'name', 'description', 'color', 'created_by', 'project_manager_id', 'created_at')
                ->latest()
                ->limit(500)
                ->get()
                ->each(function ($team) {
                    $team->loadCount('users');
                })
                ->map(function ($team) {
                    return [
                        'id' => $team->id,
                        'name' => $team->name,
                        'description' => $team->description,
                        'color' => $team->color,
                        'members_count' => $team->users_count,
                        'creator' => $team->creator?->name,
                        'project_manager' => $team->projectManager ? [
                            'id' => $team->projectManager->id,
                            'name' => $team->projectManager->name,
                            'avatar_url' => $team->projectManager->avatar_url,
                        ] : null,
                        'members' => $team->users->map(function ($user) {
                            return [
                                'id' => $user->id,
                                'name' => $user->name,
                                'email' => $user->email,
                                'role' => $user->role?->display_name,
                                'avatar_url' => $user->avatar_url,
                            ];
                        }),
                        'created_at' => $team->created_at->format('d M Y'),
                    ];
                });
        });

        $allUsers = Cache::remember('users.for-teams', 600, function () {
            return User::with('role:id,name,display_name')
                ->select('id', 'name', 'email', 'role_id', 'avatar_path')
                ->orderBy('name')
                ->get();
        });

        return Inertia::render('teams/page', [
            'teams' => $teams,
            'allUsers' => $allUsers,
        ]);
    }

    /**
     * Store a newly created team.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Team::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:teams,name',
            'description' => 'nullable|string|max:1000',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'project_manager_id' => 'required|exists:users,id',
        ]);

        Team::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'color' => $validated['color'],
            'created_by' => $request->user()->id,
            'project_manager_id' => $validated['project_manager_id'],
        ]);

        Cache::forget('teams.index');

        return back()->with('success', 'Team created successfully!');
    }

    /**
     * Update the specified team.
     */
    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:teams,name,' . $team->id,
            'description' => 'nullable|string|max:1000',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'project_manager_id' => 'required|exists:users,id',
        ]);

        $team->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'color' => $validated['color'],
            'project_manager_id' => $validated['project_manager_id'],
        ]);

        Cache::forget('teams.index');

        return back()->with('success', 'Team updated successfully!');
    }

    /**
     * Remove the specified team.
     */
    public function destroy(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        if (Project::where('team_id', $team->id)->exists()) {
            return back()->with('error', 'Tim tidak dapat dihapus karena masih digunakan oleh proyek.');
        }

        $team->users()->detach();
        $team->delete();

        Cache::forget('teams.index');
        Cache::forget('users.for-teams');

        return back()->with('success', 'Tim berhasil dihapus.');
    }

    /**
     * Add a member to a team.
     */
    public function addMember(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('manageMembers', $team);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        if ($team->users()->where('user_id', $validated['user_id'])->exists()) {
            return back()->withErrors([
                'user_id' => 'This user is already a member of the team.',
            ]);
        }

        $team->users()->attach($validated['user_id']);

        $user = User::find($validated['user_id']);
        $user->notify(new \App\Notifications\TeamAssignedNotification(
            $team->id,
            $team->name,
            $request->user()->name
        ));

        Cache::forget('teams.index');

        return back()->with('success', 'Member added successfully!');
    }

    /**
     * Remove a member from a team.
     */
    public function removeMember(Request $request, Team $team, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $team);

        if (!$team->users()->where('user_id', $user->id)->exists()) {
            return back()->withErrors([
                'message' => 'This user is not a member of the team.',
            ]);
        }

        $team->users()->detach($user->id);

        $user->notify(new \App\Notifications\TeamRemovedNotification(
            $team->id,
            $team->name,
            $request->user()->name
        ));

        Cache::forget('teams.index');

        return back()->with('success', 'Member removed successfully!');
    }
}
