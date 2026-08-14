<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class RoleController extends Controller
{
    private const LEGACY_ALIASES = [
        'companies.manage' => 'manage-company',
        'clients.manage' => 'manage-clients',
        'task-types.manage' => 'manage-task-types',
        'users.manage' => 'manage-users',
        'roles.manage' => 'manage-roles',
        'teams.create' => 'create-team',
        'teams.update' => 'update-team',
        'teams.delete' => 'delete-team',
        'teams.manage-members' => 'manage-team-members',
        'projects.create' => 'create-project',
        'projects.update-any' => 'update-any-project',
        'projects.delete' => 'delete-project',
        'timelines.create' => 'create-timeline',
        'timelines.update' => 'update-timeline',
        'timelines.delete' => 'delete-timeline',
        'tickets.create' => 'create-ticket',
        'tickets.update' => 'update-ticket',
        'tickets.update-any' => 'update-any-ticket',
        'tickets.delete' => 'delete-ticket',
        'tickets.approve' => 'approve-tickets',
        'daily-logs.create' => 'create-daily-log',
        'daily-logs.delete' => 'delete-daily-log',
        'daily-logs.manage' => 'manage-daily-logs',
    ];

    public function index()
    {
        $roles = Role::with('permissions:id,name')->get();
        $permissions = Permission::select('id', 'name', 'display_name', 'description')->get();

        return Inertia::render('roles/page', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name|max:255',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'],
        ]);

        $this->syncPermissions($role, $validated['permissions'] ?? []);
        $this->forgetRoleCaches();

        return redirect()->route('roles.index')->with('success', 'Role berhasil ditambahkan.');
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'display_name' => $validated['display_name'],
            'description' => $validated['description'],
        ]);

        $this->syncPermissions($role, $validated['permissions'] ?? []);
        $this->forgetRoleCaches();

        return redirect()->route('roles.index')->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, ['admin', 'super_admin'])) {
            return redirect()->route('roles.index')->with('error', 'Role "' . $role->display_name . '" tidak dapat dihapus.');
        }

        if ($role->users()->exists()) {
            return redirect()->route('roles.index')->with('error', 'Role tidak dapat dihapus karena masih digunakan oleh ' . $role->users()->count() . ' user.');
        }

        $role->permissions()->detach();
        $role->delete();
        $this->forgetRoleCaches();

        return redirect()->route('roles.index')->with('success', 'Role berhasil dihapus.');
    }

    /**
     * @param  array<int, int|string>  $permissionIds
     */
    private function syncPermissions(Role $role, array $permissionIds): void
    {
        if ($permissionIds === []) {
            $role->permissions()->sync([]);
            return;
        }

        $permissionNames = Permission::whereIn('id', $permissionIds)->pluck('name')->all();
        $namesToSync = $permissionNames;

        foreach ($permissionNames as $name) {
            if (isset(self::LEGACY_ALIASES[$name])) {
                $namesToSync[] = self::LEGACY_ALIASES[$name];
            }
        }

        $idsToSync = Permission::whereIn('name', array_unique($namesToSync))->pluck('id');
        $role->permissions()->sync($idsToSync);
    }

    private function forgetRoleCaches(): void
    {
        Cache::forget('roles.all');
        Cache::forget('teams.index');
        Cache::forget('users.for-teams');
        Cache::forget('users.for-tickets');
        Cache::forget('projects.index');
    }
}
