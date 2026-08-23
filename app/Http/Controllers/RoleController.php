<?php

namespace App\Http\Controllers;

use App\Models\CompanyUser;
use App\Models\Permission;
use App\Models\Role;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        $companyId = app(CurrentCompany::class)->id();
        $roles = Role::query()
            ->availableToCompany($companyId)
            ->with('permissions:id,name')
            ->orderByDesc('company_id')
            ->orderBy('display_name')
            ->get();
        $permissions = Permission::select('id', 'name', 'display_name', 'description')->get();

        return Inertia::render('roles/page', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = app(CurrentCompany::class)->id();
        abort_unless($companyId !== null, 404);

        $request->merge(['name' => Str::lower(trim((string) $request->input('name')))]);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::notIn(['admin', 'super_admin']),
                Rule::unique('roles', 'name')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'],
        ]);

        $this->syncPermissions($role, $validated['permissions'] ?? []);
        $this->forgetRoleCaches();

        return redirect()->route('roles.index')->with('success', __('messages.role.created'));
    }

    public function update(Request $request, Role $role)
    {
        $this->ensureRoleBelongsToCurrentContext($role);

        if ($role->isSystem()) {
            return redirect()->route('roles.index')->with('error', __('messages.role.cannot_edit_system'));
        }

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

        return redirect()->route('roles.index')->with('success', __('messages.role.updated'));
    }

    public function destroy(Role $role)
    {
        $this->ensureRoleBelongsToCurrentContext($role);

        if ($role->isSystem() || in_array($role->name, ['admin', 'super_admin'], true)) {
            return redirect()->route('roles.index')->with('error', __('messages.role.cannot_delete_locked', ['name' => $role->display_name]));
        }

        $membershipCount = CompanyUser::where('role_id', $role->id)->count();
        if ($membershipCount > 0) {
            return redirect()->route('roles.index')->with('error', __('messages.role.cannot_delete_in_use', ['count' => $membershipCount]));
        }

        $role->permissions()->detach();
        $role->delete();
        $this->forgetRoleCaches();

        return redirect()->route('roles.index')->with('success', __('messages.role.deleted'));
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

    private function ensureRoleBelongsToCurrentContext(Role $role): void
    {
        $companyId = app(CurrentCompany::class)->id();

        abort_unless($role->company_id === null || $role->company_id === $companyId, 404);
    }
}
