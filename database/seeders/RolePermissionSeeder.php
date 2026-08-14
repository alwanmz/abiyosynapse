<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the canonical permission catalog and the default role -> permission
 * matrix for the 4 supported roles.
 *
 * Permissions are grouped by module via the slug prefix (e.g. `tickets.*`,
 * `projects.*`) so the Hak Akses UI can render them in clean sections
 * without an extra `group` column.
 *
 * Re-run any time the catalog changes — the existing role assignments are
 * synced (resetting roles back to their canonical defaults), so make sure
 * any custom roles added at runtime are preserved by adding them to the
 * matrix below before reseeding.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Module-grouped permission catalog.
     *
     * Keep slugs in `module.action` form so the UI groups by `module`.
     */
    private const PERMISSIONS = [
        // -- Master Data -------------------------------------------------
        'companies.view'     => 'Melihat profil perusahaan',
        'companies.edit'     => 'Mengubah profil perusahaan',
        'companies.manage'   => 'Mengelola profil perusahaan (legacy)',

        'clients.view'       => 'Melihat data klien (master)',
        'clients.create'     => 'Membuat data klien',
        'clients.edit'       => 'Mengubah data klien',
        'clients.delete'     => 'Menghapus data klien',
        'clients.manage'     => 'Mengelola data klien (legacy)',

        'task-types.view'    => 'Melihat jenis tugas (master)',
        'task-types.create'  => 'Membuat jenis tugas',
        'task-types.edit'    => 'Mengubah jenis tugas',
        'task-types.delete'  => 'Menghapus jenis tugas',
        'task-types.manage'  => 'Mengelola jenis tugas (legacy)',

        'users.view'         => 'Melihat pengguna sistem',
        'users.create'       => 'Membuat pengguna sistem',
        'users.edit'         => 'Mengubah pengguna sistem',
        'users.delete'       => 'Menghapus pengguna sistem',
        'users.manage'       => 'Mengelola pengguna sistem (legacy)',

        'roles.view'         => 'Melihat role & hak akses',
        'roles.create'       => 'Membuat role & hak akses',
        'roles.edit'         => 'Mengubah role & hak akses',
        'roles.delete'       => 'Menghapus role & hak akses',
        'roles.manage'       => 'Mengelola role & hak akses (legacy)',

        // -- Teams -------------------------------------------------------
        'teams.view'           => 'Melihat tim',
        'teams.create'         => 'Membuat tim baru',
        'teams.edit'           => 'Mengubah tim',
        'teams.update'         => 'Mengubah tim (legacy)',
        'teams.delete'         => 'Menghapus tim',
        'teams.manage-members' => 'Menambah / menghapus anggota tim',

        // -- Projects ----------------------------------------------------
        'projects.view'       => 'Melihat proyek',
        'projects.create'     => 'Membuat proyek',
        'projects.edit'       => 'Mengubah proyek',
        'projects.update-any' => 'Mengubah semua proyek (legacy)',
        'projects.delete'     => 'Menghapus proyek',

        // -- Timelines ---------------------------------------------------
        'timelines.view'   => 'Melihat linimasa',
        'timelines.create' => 'Membuat linimasa',
        'timelines.edit'   => 'Mengubah linimasa',
        'timelines.update' => 'Mengubah linimasa (legacy)',
        'timelines.delete' => 'Menghapus linimasa',

        // -- Tickets -----------------------------------------------------
        'tickets.view'       => 'Melihat tiket',
        'tickets.create'     => 'Membuat tiket baru',
        'tickets.edit'       => 'Mengubah tiket yang ditugaskan',
        'tickets.update'     => 'Mengubah tiket yang ditugaskan (legacy)',
        'tickets.update-any' => 'Mengubah semua tiket di tim',
        'tickets.delete'     => 'Menghapus tiket',
        'tickets.approve'    => 'Approve / reject / delegasi tiket',

        // -- Daily Logs --------------------------------------------------
        'daily-logs.view'   => 'Melihat catatan harian',
        'daily-logs.create' => 'Membuat catatan harian',
        'daily-logs.delete' => 'Menghapus catatan harian sendiri',
        'daily-logs.manage' => 'Melihat & mengelola semua catatan harian',

        // -- Minutes -----------------------------------------------------
        'minutes.view'   => 'Melihat notulensi',
        'minutes.create' => 'Membuat notulensi',
        'minutes.edit'   => 'Mengubah notulensi',
        'minutes.update' => 'Mengubah notulensi (legacy)',
        'minutes.delete' => 'Menghapus notulensi',

        // -- Guidebook ---------------------------------------------------
        'guidebooks.view'   => 'Melihat guidebook & SOP',
        'guidebooks.manage' => 'Membuat, mengubah & menghapus guidebook',

        // -- Maintenance Reports -----------------------------------------
        'maintenance-reports.view'   => 'Melihat & mengunduh laporan maintenance',
        'maintenance-reports.manage' => 'Membuat, mengubah & menerbitkan laporan maintenance',

        // -- Reports & Analytics ----------------------------------------
        'analytics.view' => 'Melihat analitik',
        'reports.view'   => 'Melihat laporan dan ekspor',
    ];

    /**
     * Backwards-compatibility map for the old flat slugs that some routes
     * and the EnsureUserHasPermission middleware still reference. We seed
     * BOTH old and new slugs so existing route definitions keep working
     * while we migrate to the dotted convention.
     *
     * Each new slug is also seeded under its legacy alias.
     */
    private const LEGACY_ALIASES = [
        'companies.manage'     => 'manage-company',
        'clients.manage'       => 'manage-clients',
        'task-types.manage'    => 'manage-task-types',
        'users.manage'         => 'manage-users',
        'roles.manage'         => 'manage-roles',
        'teams.create'         => 'create-team',
        'teams.update'         => 'update-team',
        'teams.delete'         => 'delete-team',
        'teams.manage-members' => 'manage-team-members',
        'projects.create'      => 'create-project',
        'projects.update-any'  => 'update-any-project',
        'projects.delete'      => 'delete-project',
        'timelines.create'     => 'create-timeline',
        'timelines.update'     => 'update-timeline',
        'timelines.delete'     => 'delete-timeline',
        'tickets.create'       => 'create-ticket',
        'tickets.update'       => 'update-ticket',
        'tickets.update-any'   => 'update-any-ticket',
        'tickets.delete'       => 'delete-ticket',
        'tickets.approve'      => 'approve-tickets',
        'daily-logs.create'    => 'create-daily-log',
        'daily-logs.delete'    => 'delete-daily-log',
        'daily-logs.manage'    => 'manage-daily-logs',
    ];

    public function run(): void
    {
        // 1. Seed all canonical permissions.
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $this->slugToTitle($name),
                    'description' => $description,
                ]
            );
        }

        // 2. Seed legacy aliases so middleware/routes keep working.
        foreach (self::LEGACY_ALIASES as $modern => $legacy) {
            Permission::updateOrCreate(
                ['name' => $legacy],
                [
                    'display_name' => $this->slugToTitle($legacy),
                    'description' => self::PERMISSIONS[$modern] . ' (legacy alias)',
                ]
            );
        }

        // 3. Default permission matrix for the 4 supported roles.
        // We sync BOTH the modern slug and its legacy alias so no
        // controller/middleware breaks during the migration.
        $matrix = [
            // CEO/COO/CMO — gets everything (also bypassed via isAdmin()).
            'super_admin' => array_merge(
                array_keys(self::PERMISSIONS),
                array_values(self::LEGACY_ALIASES),
            ),

            // Mengontrol project + approve/reject/delegasi tiket.
            // Master data (Company/Client/Task Type/User/Role) TIDAK termasuk.
            'project_manager' => $this->expand([
                'teams.view', 'teams.create', 'teams.edit', 'teams.update', 'teams.delete', 'teams.manage-members',
                'projects.view', 'projects.create', 'projects.edit', 'projects.update-any', 'projects.delete',
                'timelines.view', 'timelines.create', 'timelines.edit', 'timelines.update', 'timelines.delete',
                'tickets.view', 'tickets.create', 'tickets.edit', 'tickets.update', 'tickets.update-any', 'tickets.delete',
                'tickets.approve',
                'daily-logs.view', 'daily-logs.create', 'daily-logs.delete', 'daily-logs.manage',
                'minutes.view', 'minutes.create', 'minutes.edit', 'minutes.update', 'minutes.delete',
                'guidebooks.view', 'guidebooks.manage',
                'maintenance-reports.view', 'maintenance-reports.manage',
            ]),

            // Mengirim tiket + isi progress harian.
            'implementator' => $this->expand([
                'teams.view', 'projects.view', 'timelines.view',
                'tickets.view', 'tickets.create', 'tickets.edit', 'tickets.update',
                'daily-logs.view', 'daily-logs.create', 'daily-logs.delete',
                'minutes.view', 'minutes.create',
                'guidebooks.view',
                'maintenance-reports.view',
            ]),

            // Memproses tiket + isi progress harian.
            'programmer' => $this->expand([
                'teams.view', 'projects.view', 'timelines.view',
                'tickets.view', 'tickets.edit', 'tickets.update',
                'daily-logs.view', 'daily-logs.create', 'daily-logs.delete',
                'minutes.view',
                'guidebooks.view',
            ]),
        ];

        foreach (Role::all() as $role) {
            $perms = $matrix[$role->name] ?? null;
            if ($perms === null) {
                // Custom role created at runtime — leave its permissions alone.
                continue;
            }
            $permIds = Permission::whereIn('name', $perms)->pluck('id');
            $role->permissions()->sync($permIds);
        }
    }

    /**
     * Expand a list of modern slugs to include their legacy aliases too.
     *
     * @param  array<int, string>  $slugs
     * @return array<int, string>
     */
    private function expand(array $slugs): array
    {
        $out = [];
        foreach ($slugs as $slug) {
            $out[] = $slug;
            if (isset(self::LEGACY_ALIASES[$slug])) {
                $out[] = self::LEGACY_ALIASES[$slug];
            }
        }
        return $out;
    }

    /**
     * Turn `tickets.update-any` or `manage-clients` into "Tickets Update Any"
     * / "Manage Clients" for a default human-readable label.
     */
    private function slugToTitle(string $slug): string
    {
        return ucwords(str_replace(['.', '-', '_'], ' ', $slug));
    }
}
