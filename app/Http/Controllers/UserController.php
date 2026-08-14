<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): Response
    {
        $perPage = $request->get('per_page', 10);

        $users = User::with('role')
            ->select('id', 'name', 'username', 'email', 'avatar_path', 'email_verified_at', 'role_id', 'created_at')
            ->latest()
            ->paginate($perPage)
            ->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'avatar_url' => $user->avatar_url,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'display_name' => $user->getRoleDisplayName(),
                    ] : null,
                    'created_at' => $user->created_at->format('d M Y'),
                ];
            });

        $roles = Role::query()
            ->select('id', 'name', 'display_name')
            ->orderBy('display_name')
            ->get();

        return Inertia::render('manage-users/page', [
            'users' => $users,
            'roles' => $roles
        ]);
    }

    /**
     * Store a newly created user.
     *
     * `avatar` is an optional pre-cropped square image (jpg/png/gif/webp,
     * max 2 MB) — frontend already cropped it to a circle so we just save
     * the file as-is.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|alpha_dash|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:4|confirmed',
            'role_id' => 'required|exists:roles,id',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        User::create([
            'name' => $validated['name'],
            'username' => strtolower($validated['username']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'avatar_path' => $avatarPath,
            'email_verified_at' => now(),
        ]);

        $this->forgetUserCaches();

        return redirect()->back()->with('success', 'User created successfully.');
    }

    /**
     * Update the user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|alpha_dash|unique:users,username,' . $user->id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
            'remove_avatar' => 'nullable|boolean',
        ]);

        $payload = [
            'name' => $validated['name'],
            'username' => strtolower($validated['username']),
            'email' => $validated['email'],
        ];

        if ($request->hasFile('avatar')) {
            // Replace any previous photo, best-effort cleanup.
            $this->safelyDeleteAvatar($user->avatar_path);
            $payload['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar')) {
            $this->safelyDeleteAvatar($user->avatar_path);
            $payload['avatar_path'] = null;
        }

        $user->update($payload);
        $this->forgetUserCaches();

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    /**
     * Update the user's role.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $oldRole = $user->role?->display_name ?? 'No Role';

        $user->update([
            'role_id' => $validated['role_id']
        ]);
        $this->forgetUserCaches();

        $user->load('role');
        $newRole = $user->role->display_name;

        $user->notify(new \App\Notifications\RoleAssignedNotification(
            $oldRole,
            $newRole,
            $request->user()->name
        ));

        return redirect()->back()->with('success', 'User role updated successfully.');
    }

    /**
     * Delete the user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        // Guard against deleting the last super_admin (formerly 'admin').
        $superAdminRole = Role::whereIn('name', ['super_admin', 'admin'])->first();
        if ($superAdminRole && $user->role_id === $superAdminRole->id) {
            $count = User::where('role_id', $superAdminRole->id)->count();
            if ($count <= 1) {
                return redirect()->back()->with('error', 'Cannot delete the last admin account.');
            }
        }

        $this->safelyDeleteAvatar($user->avatar_path);
        $user->delete();
        $this->forgetUserCaches();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }

    protected function forgetUserCaches(): void
    {
        Cache::forget('roles.all');
        Cache::forget('teams.index');
        Cache::forget('users.for-teams');
        Cache::forget('users.for-tickets');
        Cache::forget('projects.index');
    }

    /**
     * Best-effort avatar file cleanup. A missing or unwritable file
     * should never block the parent operation — we log and continue.
     */
    protected function safelyDeleteAvatar(?string $path): void
    {
        if (! $path) {
            return;
        }
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete user avatar', [
                'path' => $path,
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
