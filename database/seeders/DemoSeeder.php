<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\DailyLog;
use App\Models\Minute;
use App\Models\Project;
use App\Models\ProjectTimeline;
use App\Models\Role;
use App\Models\TaskType;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Memulai Demo Seeder...');

        $this->seedCompany();
        $users    = $this->seedUsers();
        $teams    = $this->seedTeams($users);
        $clients  = $this->seedClients();
        $taskTypes = $this->seedTaskTypes();
        $projects = $this->seedProjects($teams, $clients, $users);
        $timelines = $this->seedTimelines($projects);
        $this->seedTickets($projects, $timelines, $taskTypes, $users);
        $this->seedDailyLogs($users);
        $this->seedMinutes($projects, $users);

        $this->command->info('✅ Demo data berhasil dibuat!');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Super Admin',      'admin@demo.ski',         'demo1234'],
                ['Project Manager',  'pm@demo.ski',            'demo1234'],
                ['Programmer',       'budi@demo.ski',          'demo1234'],
                ['Programmer',       'sari@demo.ski',          'demo1234'],
                ['Implementator',    'rudi@demo.ski',          'demo1234'],
            ]
        );
    }

    private function seedCompany(): void
    {
        CompanySetting::updateOrCreate(['id' => 1], [
            'kode'             => 'SKI',
            'nama_perusahaan'  => 'PT Sistem Kesehatan Indonesia',
            'alamat'           => 'Jl. Sudirman No. 42, Jakarta Selatan',
            'telp'             => '021-5550123',
            'email'            => 'info@sistemkesehatan.id',
        ]);
        $this->command->info('  ✓ Company setting');
    }

    private function seedUsers(): array
    {
        $roles = Role::pluck('id', 'name');

        $users = [];

        $definitions = [
            ['name' => 'Admin Demo',        'username' => 'admindemo',  'email' => 'admin@demo.ski',  'role' => 'super_admin'],
            ['name' => 'Rina Kusuma',        'username' => 'rinakusuma', 'email' => 'pm@demo.ski',     'role' => 'project_manager'],
            ['name' => 'Budi Santoso',       'username' => 'budisantoso','email' => 'budi@demo.ski',   'role' => 'programmer'],
            ['name' => 'Sari Dewi',          'username' => 'saridewi',   'email' => 'sari@demo.ski',   'role' => 'programmer'],
            ['name' => 'Rudi Hartono',       'username' => 'rudihartono','email' => 'rudi@demo.ski',   'role' => 'implementator'],
        ];

        foreach ($definitions as $def) {
            $users[$def['username']] = User::updateOrCreate(
                ['email' => $def['email']],
                [
                    'name'              => $def['name'],
                    'username'          => $def['username'],
                    'password'          => Hash::make('demo1234'),
                    'role_id'           => $roles[$def['role']] ?? null,
                    'email_verified_at' => now(),
                ]
            );
        }

        $this->command->info('  ✓ Users (5 akun demo)');
        return $users;
    }

    private function seedTeams(array $users): array
    {
        $teams = [];

        $definitions = [
            ['name' => 'Tim Backend',   'description' => 'Pengembangan API dan server-side', 'pm' => 'rinakusuma', 'members' => ['budisantoso', 'saridewi']],
            ['name' => 'Tim Lapangan',  'description' => 'Implementasi dan onboarding klien', 'pm' => 'rinakusuma', 'members' => ['rudihartono']],
        ];

        foreach ($definitions as $def) {
            $team = Team::updateOrCreate(['name' => $def['name']], [
                'description'        => $def['description'],
                'project_manager_id' => $users[$def['pm']]->id,
                'created_by'         => $users['admindemo']->id,
            ]);

            $memberIds = array_map(fn($u) => $users[$u]->id, $def['members']);
            $team->users()->syncWithoutDetaching($memberIds);
            $teams[$def['name']] = $team;
        }

        $this->command->info('  ✓ Teams (2 tim)');
        return $teams;
    }

    private function seedClients(): array
    {
        $clients = [];
        $definitions = [
            ['kode' => 'RSUD-BDG', 'nama' => 'RSUD Kota Bandung',       'kontak' => '022-7234567', 'is_active' => true],
            ['kode' => 'RS-HSN',   'nama' => 'RS Hasan Sadikin',         'kontak' => '022-2034953', 'is_active' => true],
            ['kode' => 'PKM-DIG',  'nama' => 'Puskesmas Digital Depok',  'kontak' => '021-7541234', 'is_active' => true],
            ['kode' => 'RSIA-BDG', 'nama' => 'RSIA Hermina Bandung',     'kontak' => '022-6120129', 'is_active' => false],
        ];

        foreach ($definitions as $def) {
            $clients[$def['kode']] = Client::updateOrCreate(['kode' => $def['kode']], $def);
        }

        $this->command->info('  ✓ Clients (4 klien)');
        return $clients;
    }

    private function seedTaskTypes(): array
    {
        $types = [];
        $names = ['Pengembangan Fitur', 'Perbaikan Bug', 'Analisis & Desain', 'Testing & QA', 'Implementasi', 'Dokumentasi', 'Integrasi Sistem'];
        foreach ($names as $name) {
            $types[$name] = TaskType::updateOrCreate(['nama' => $name]);
        }
        $this->command->info('  ✓ Task Types (7 jenis)');
        return $types;
    }

    private function seedProjects(array $teams, array $clients, array $users): array
    {
        $projects = [];
        $backend  = $teams['Tim Backend'];
        $lapangan = $teams['Tim Lapangan'];
        $pm       = $users['rinakusuma'];
        $admin    = $users['admindemo'];

        $definitions = [
            [
                'name'              => 'SIMRS - Modul Rawat Inap',
                'kode_project'      => 'SKI-001',
                'status'            => 'in_progress',
                'team'              => $backend,
                'client_key'        => 'RSUD-BDG',
                'start_date'        => now()->subMonths(3),
                'end_date'          => now()->addMonths(2),
                'jenis_pekerjaan'   => 'implementasi',
            ],
            [
                'name'              => 'Aplikasi Antrian Digital',
                'kode_project'      => 'SKI-002',
                'status'            => 'completed',
                'team'              => $lapangan,
                'client_key'        => 'RS-HSN',
                'start_date'        => now()->subMonths(6),
                'end_date'          => now()->subMonths(1),
                'jenis_pekerjaan'   => 'implementasi',
            ],
            [
                'name'              => 'Portal Pasien Online',
                'kode_project'      => 'SKI-003',
                'status'            => 'planning',
                'team'              => $backend,
                'client_key'        => 'PKM-DIG',
                'start_date'        => now()->addWeeks(2),
                'end_date'          => now()->addMonths(4),
                'jenis_pekerjaan'   => 'implementasi',
            ],
            [
                'name'              => 'E-Resep & Farmasi Integration',
                'kode_project'      => 'SKI-004',
                'status'            => 'in_progress',
                'team'              => $backend,
                'client_key'        => 'RSUD-BDG',
                'start_date'        => now()->subMonths(1),
                'end_date'          => now()->addMonths(3),
                'jenis_pekerjaan'   => 'maintenance',
            ],
        ];

        foreach ($definitions as $def) {
            $p = Project::updateOrCreate(['kode_project' => $def['kode_project']], [
                'name'               => $def['name'],
                'status'             => $def['status'],
                'team_id'            => $def['team']->id,
                'client_id'          => $clients[$def['client_key']]->id,
                'project_manager_id' => $pm->id,
                'created_by'         => $admin->id,
                'start_date'         => $def['start_date'],
                'end_date'           => $def['end_date'],
                'jenis_pekerjaan'    => $def['jenis_pekerjaan'],
            ]);
            $projects[$def['kode_project']] = $p;
        }

        $this->command->info('  ✓ Projects (4 proyek)');
        return $projects;
    }

    private function seedTimelines(array $projects): array
    {
        $timelines = [];

        $definitions = [
            ['project' => 'SKI-001', 'title' => 'Sprint 1 - Setup & Auth',          'status' => 'completed', 'type' => 'sprint', 'sprint_number' => 1, 'start' => now()->subMonths(3), 'end' => now()->subMonths(2)->subWeeks(2)],
            ['project' => 'SKI-001', 'title' => 'Sprint 2 - Modul Pendaftaran',      'status' => 'completed', 'type' => 'sprint', 'sprint_number' => 2, 'start' => now()->subMonths(2)->subWeeks(2), 'end' => now()->subMonths(1)->subWeeks(2)],
            ['project' => 'SKI-001', 'title' => 'Sprint 3 - Rawat Inap & Kamar',     'status' => 'in_progress','type' => 'sprint', 'sprint_number' => 3, 'start' => now()->subMonths(1)->subWeeks(2), 'end' => now()->addWeeks(2)],
            ['project' => 'SKI-002', 'title' => 'Fase Analisis',                     'status' => 'completed', 'type' => 'phase',  'sprint_number' => null, 'start' => now()->subMonths(6), 'end' => now()->subMonths(5)],
            ['project' => 'SKI-002', 'title' => 'Fase Implementasi & Go-Live',       'status' => 'completed', 'type' => 'phase',  'sprint_number' => null, 'start' => now()->subMonths(5), 'end' => now()->subMonths(1)],
            ['project' => 'SKI-004', 'title' => 'Sprint 1 - Analisis API Farmasi',   'status' => 'in_progress','type' => 'sprint', 'sprint_number' => 1, 'start' => now()->subMonths(1), 'end' => now()->addWeeks(3)],
        ];

        foreach ($definitions as $i => $def) {
            $tl = ProjectTimeline::updateOrCreate(
                ['project_id' => $projects[$def['project']]->id, 'title' => $def['title']],
                [
                    'type'          => $def['type'],
                    'phase'         => 'development',
                    'status'        => $def['status'],
                    'sprint_number' => $def['sprint_number'],
                    'start_date'    => $def['start'],
                    'end_date'      => $def['end'],
                ]
            );
            $timelines["tl{$i}"] = $tl;
        }

        $this->command->info('  ✓ Timelines (6 sprint/fase)');
        return $timelines;
    }

    private function seedTickets(array $projects, array $timelines, array $taskTypes, array $users): void
    {
        $budi  = $users['budisantoso'];
        $sari  = $users['saridewi'];
        $rudi  = $users['rudihartono'];
        $pm    = $users['rinakusuma'];
        $admin = $users['admindemo'];
        $p1    = $projects['SKI-001'];
        $p2    = $projects['SKI-002'];
        $p4    = $projects['SKI-004'];
        $tl2   = $timelines['tl1']; // Sprint 2 completed
        $tl3   = $timelines['tl2']; // Sprint 3 in_progress
        $tl5   = $timelines['tl4']; // SKI-002 implementasi completed
        $tl6   = $timelines['tl5']; // SKI-004 sprint 1

        $taskDev  = $taskTypes['Pengembangan Fitur'];
        $taskBug  = $taskTypes['Perbaikan Bug'];
        $taskImpl = $taskTypes['Implementasi'];
        $taskTest = $taskTypes['Testing & QA'];

        // Keys: proj, tl, title, status, priority, assigned, reporter, type, est, due
        // Optional: delegated (User), rejected (bool), rejection_reason (string)
        $tickets = [
            // ── SKI-001 Sprint 3 (sedang berjalan) ──────────────────────────────────
            // Tiket ini sudah di-approve & didelegasikan → contoh alur selesai
            [
                'proj' => $p1, 'tl' => $tl3,
                'title'    => 'Implementasi form pendaftaran rawat inap',
                'status'   => 'inprogress', 'priority' => 'high',
                'assigned' => $budi, 'reporter' => $pm, 'type' => $taskDev,
                'est' => 8, 'due' => now()->addDays(5),
                'delegated' => $sari,
            ],
            // Sudah selesai & approved
            [
                'proj' => $p1, 'tl' => $tl3,
                'title'    => 'API endpoint data kamar dan ketersediaan',
                'status'   => 'done', 'priority' => 'high',
                'assigned' => $budi, 'reporter' => $pm, 'type' => $taskDev,
                'est' => 6, 'due' => now()->subDays(3),
            ],
            // ★ DEMO APPROVAL: tiket Pengembangan Fitur, status todo, belum di-approve
            [
                'proj' => $p1, 'tl' => $tl3,
                'title'    => 'Halaman manajemen kamar (admin)',
                'status'   => 'todo', 'priority' => 'medium',
                'assigned' => $sari, 'reporter' => $pm, 'type' => $taskDev,
                'est' => 5, 'due' => now()->addDays(7),
            ],
            // Bug — tidak perlu approval
            [
                'proj' => $p1, 'tl' => $tl3,
                'title'    => 'Bug: form tidak tersimpan jika koneksi lambat',
                'status'   => 'inprogress', 'priority' => 'highest',
                'assigned' => $budi, 'reporter' => $sari, 'type' => $taskBug,
                'est' => 2, 'due' => now()->addDays(1),
            ],
            // Testing — tidak perlu approval, sudah di qa-ready
            [
                'proj' => $p1, 'tl' => $tl3,
                'title'    => 'Testing integrasi modul pendaftaran pasien',
                'status'   => 'qa-ready', 'priority' => 'medium',
                'assigned' => $sari, 'reporter' => $pm, 'type' => $taskTest,
                'est' => 4, 'due' => now()->addDays(3),
            ],
            // Review
            [
                'proj' => $p1, 'tl' => $tl3,
                'title'    => 'Review desain UI pendaftaran oleh PM & klien',
                'status'   => 'review', 'priority' => 'medium',
                'assigned' => $sari, 'reporter' => $pm, 'type' => $taskTest,
                'est' => 2, 'due' => now()->addDays(2),
            ],
            // ★ DEMO REJECTION: Pengembangan Fitur ditolak → tidak bisa pindah status
            [
                'proj' => $p1, 'tl' => $tl3,
                'title'    => 'Fitur export laporan harian pasien ke PDF',
                'status'   => 'todo', 'priority' => 'low',
                'assigned' => $sari, 'reporter' => $pm, 'type' => $taskDev,
                'est' => 5, 'due' => now()->addDays(14),
                'rejected'          => true,
                'rejection_reason'  => 'Scope terlalu besar untuk sprint ini, perlu dipecah dulu menjadi beberapa tiket terpisah.',
            ],
            // Draft (backlog)
            [
                'proj' => $p1, 'tl' => $tl3,
                'title'    => 'Notifikasi email konfirmasi rawat inap',
                'status'   => 'backlog', 'priority' => 'low',
                'assigned' => $sari, 'reporter' => $pm, 'type' => $taskDev,
                'est' => 3, 'due' => now()->addDays(20),
            ],
            // ── SKI-001 Sprint 2 (done) ──────────────────────────────────────────────
            [
                'proj' => $p1, 'tl' => $tl2,
                'title'    => 'Setup database schema pasien',
                'status'   => 'done', 'priority' => 'high',
                'assigned' => $budi, 'reporter' => $pm, 'type' => $taskDev,
                'est' => 4, 'due' => now()->subMonths(2),
            ],
            [
                'proj' => $p1, 'tl' => $tl2,
                'title'    => 'Autentikasi dokter dan staf RS',
                'status'   => 'done', 'priority' => 'high',
                'assigned' => $sari, 'reporter' => $pm, 'type' => $taskDev,
                'est' => 5, 'due' => now()->subMonths(2),
            ],
            [
                'proj' => $p1, 'tl' => $tl2,
                'title'    => 'Dashboard ringkasan harian',
                'status'   => 'done', 'priority' => 'medium',
                'assigned' => $sari, 'reporter' => $pm, 'type' => $taskDev,
                'est' => 6, 'due' => now()->subMonths(2),
            ],
            // ── SKI-002 (proyek selesai) ─────────────────────────────────────────────
            [
                'proj' => $p2, 'tl' => $tl5,
                'title'    => 'Setup kiosk antrian di lobi RS',
                'status'   => 'done', 'priority' => 'high',
                'assigned' => $rudi, 'reporter' => $pm, 'type' => $taskImpl,
                'est' => 8, 'due' => now()->subMonths(2),
            ],
            [
                'proj' => $p2, 'tl' => $tl5,
                'title'    => 'Training staf admisi RS Hasan Sadikin',
                'status'   => 'done', 'priority' => 'medium',
                'assigned' => $rudi, 'reporter' => $pm, 'type' => $taskImpl,
                'est' => 4, 'due' => now()->subMonths(2),
            ],
            [
                'proj' => $p2, 'tl' => $tl5,
                'title'    => 'Bug: printer antrian kadang offline',
                'status'   => 'done', 'priority' => 'high',
                'assigned' => $budi, 'reporter' => $rudi, 'type' => $taskBug,
                'est' => 3, 'due' => now()->subMonths(1),
            ],
            // ── SKI-004 ──────────────────────────────────────────────────────────────
            [
                'proj' => $p4, 'tl' => $tl6,
                'title'    => 'Analisis API BPJS Farmasi',
                'status'   => 'inprogress', 'priority' => 'high',
                'assigned' => $budi, 'reporter' => $pm, 'type' => $taskDev,
                'est' => 6, 'due' => now()->addDays(10),
            ],
            [
                'proj' => $p4, 'tl' => $tl6,
                'title'    => 'Mapping data obat generik ke kode BPJS',
                'status'   => 'todo', 'priority' => 'medium',
                'assigned' => $sari, 'reporter' => $pm, 'type' => $taskDev,
                'est' => 4, 'due' => now()->addDays(14),
            ],
            [
                'proj' => $p4, 'tl' => $tl6,
                'title'    => 'Dokumentasi endpoint integrasi e-resep',
                'status'   => 'done', 'priority' => 'low',
                'assigned' => $sari, 'reporter' => $pm, 'type' => $taskDev,
                'est' => 3, 'due' => now()->subDays(5),
            ],
        ];

        // Approval rule: Pengembangan Fitur wajib approved sebelum bisa aktif.
        // Tiket active/done → set approved_at. Tiket todo/backlog → biarkan kosong (untuk demo).
        $needsApproval  = fn($data) => $data['type']->nama === 'Pengembangan Fitur';
        $activeStatuses = ['inprogress', 'qa-ready', 'qa-test', 'review', 'done'];
        $adminId        = $admin->id;

        $counter = 0;
        foreach ($tickets as $data) {
            $counter++;
            $num = 'SKI-TKT-' . str_pad($counter, 3, '0', STR_PAD_LEFT);

            $isRejected = !empty($data['rejected']);
            $approvedAt = null;
            $approvedBy = null;
            $rejectedAt = null;
            $rejectedBy = null;
            $rejectionReason = null;

            if ($isRejected) {
                // Rejected trumps approval — stays stuck at current status
                $rejectedAt      = now()->subDays(rand(1, 3));
                $rejectedBy      = $adminId;
                $rejectionReason = $data['rejection_reason'] ?? 'Ditolak oleh admin.';
            } elseif ($needsApproval($data) && in_array($data['status'], $activeStatuses)) {
                $approvedAt = now()->subDays(rand(2, 7));
                $approvedBy = $adminId;
            }

            $delegatedTo = isset($data['delegated']) ? $data['delegated']->id : null;
            $delegatedAt = $delegatedTo ? now()->subDays(rand(1, 3)) : null;

            Ticket::updateOrCreate(
                ['ticket_number' => $num],
                [
                    'project_id'       => $data['proj']->id,
                    'timeline_id'      => $data['tl']->id,
                    'task_type_id'     => $data['type']->id,
                    'reporter_id'      => $data['reporter']->id,
                    'assigned_to'      => $data['assigned']->id,
                    'title'            => $data['title'],
                    'type'             => 'task',
                    'priority'         => $data['priority'],
                    'status'           => $data['status'],
                    'estimated_hours'  => $data['est'],
                    'actual_hours'     => $data['status'] === 'done' ? round($data['est'] * (0.8 + (rand(0, 40) / 100)), 1) : null,
                    'due_date'         => $data['due'],
                    'resolved_at'      => $data['status'] === 'done' ? now()->subDays(rand(1, 5)) : null,
                    'approved_at'      => $approvedAt,
                    'approved_by'      => $approvedBy,
                    'rejected_at'      => $rejectedAt,
                    'rejected_by'      => $rejectedBy,
                    'rejection_reason' => $rejectionReason,
                    'delegated_to'     => $delegatedTo,
                    'delegated_at'     => $delegatedAt,
                ]
            );
        }

        $this->command->info('  ✓ Tickets (' . count($tickets) . ' tiket)');
    }

    private function seedDailyLogs(array $users): void
    {
        $budi = $users['budisantoso'];
        $sari = $users['saridewi'];
        $rudi = $users['rudihartono'];
        $pm   = $users['rinakusuma'];

        $entries = [
            // Today
            [$budi, 0, 'development', 180, 'happy', 8, 'Menyelesaikan implementasi form pendaftaran rawat inap dan integrasi dengan API kamar.'],
            [$sari, 0, 'testing',     120, 'focused', 7, 'Testing modul dashboard, ditemukan 2 minor bug pada tampilan mobile. Sudah didokumentasikan di tiket.'],
            [$pm,   0, 'meeting',      90, 'happy',  9, 'Rapat sprint review dengan client RSUD Bandung. Feedback positif, ada permintaan tambahan fitur laporan harian.'],
            // Yesterday
            [$budi, 1, 'development', 210, 'focused', 8, 'Fix bug form tidak tersimpan saat koneksi lambat. Root cause: timeout handling belum diimplementasi.'],
            [$sari, 1, 'development', 150, 'happy',  7, 'Selesaikan halaman manajemen kamar, sudah bisa CRUD data kamar beserta statusnya.'],
            [$rudi, 1, 'implementation', 240, 'happy', 8, 'Koordinasi dengan staf admisi RS Hasan Sadikin untuk pelatihan sistem antrian tahap 2.'],
            // 2 days ago
            [$budi, 2, 'development', 180, 'focused', 7, 'Mengerjakan API endpoint data kamar — selesai, sudah di-merge ke staging.'],
            [$sari, 2, 'meeting',      60, 'neutral', 6, 'Meeting internal tim untuk review progress sprint 3. Diskusi prioritas tiket backlog.'],
            [$pm,   2, 'planning',    120, 'happy',  8, 'Finalisasi scope Sprint 4, update dokumen project plan, dan koordinasi dengan klien untuk jadwal UAT.'],
            // 3 days ago
            [$rudi, 3, 'implementation', 300, 'focused', 9, 'Go-live sistem antrian digital RSIA Hermina. Semua kiosk berfungsi normal. Zero error di production.'],
            [$budi, 3, 'development', 150, 'happy',  8, 'Setup CI/CD pipeline untuk project SKI-001. Deploy ke staging berhasil.'],
            // 5 days ago
            [$sari, 5, 'testing',    180, 'focused', 7, 'Regression test setelah merge Sprint 2 ke main branch. 98% pass, 3 test case perlu di-update.'],
            [$budi, 5, 'development', 120, 'tired',  5, 'Troubleshoot masalah koneksi database di server staging. Selesai setelah 2 jam investigasi.'],
            // 7 days ago
            [$pm,   7, 'meeting',     90, 'happy',  8, 'Kick-off meeting project SKI-004 dengan tim farmasi RSUD Bandung. Dokumen SLA sudah ditandatangani.'],
            [$rudi, 7, 'implementation', 180, 'happy', 7, 'Instalasi perangkat kiosk di RSIA. Semua unit terinstall dan terhubung ke server.'],
        ];

        foreach ($entries as $i => [$user, $daysAgo, $cat, $dur, $mood, $energy, $desc]) {
            $date = now()->subDays($daysAgo)->format('Y-m-d');
            $logNum = DailyLog::nextLogNumber($date);
            if (!DailyLog::where('user_id', $user->id)->whereDate('log_date', $date)->where('category', $cat)->exists()) {
                DailyLog::create([
                    'user_id'          => $user->id,
                    'log_number'       => $logNum,
                    'log_date'         => $date,
                    'category'         => $cat,
                    'duration_minutes' => $dur,
                    'mood'             => $mood,
                    'energy_level'     => $energy,
                    'description'      => $desc,
                ]);
            }
        }

        $this->command->info('  ✓ Daily Logs (15 catatan harian)');
    }

    private function seedMinutes(array $projects, array $users): void
    {
        $pm   = $users['rinakusuma'];
        $budi = $users['budisantoso'];
        $sari = $users['saridewi'];
        $rudi = $users['rudihartono'];
        $p1   = $projects['SKI-001'];
        $p4   = $projects['SKI-004'];

        $minutesData = [
            [
                'project_id'   => $p1->id,
                'title'        => 'Sprint 3 Review & Planning - SIMRS Rawat Inap',
                'meeting_date' => now()->subDays(2)->format('Y-m-d'),
                'location'     => 'Google Meet',
                'created_by'   => $pm->id,
                'attendees'    => [['name' => $pm->name], ['name' => $budi->name], ['name' => $sari->name], ['name' => 'dr. Hendra (RSUD)']],
                'agenda'       => "1. Review progress Sprint 3\n2. Demo fitur pendaftaran rawat inap\n3. Feedback client\n4. Planning Sprint 4",
                'summary'      => 'Sprint 3 berjalan 80% sesuai target. Client puas dengan demo fitur pendaftaran. Disepakati penambahan fitur laporan harian untuk Sprint 4. Bug form save sudah di-assign ke Budi dengan deadline besok.',
                'decisions'    => [
                    ['text' => 'Tambahkan fitur laporan harian pasien di Sprint 4', 'owner_name' => $budi->name, 'due_date' => now()->addDays(14)->format('Y-m-d')],
                    ['text' => 'Fix bug form save sebelum demo berikutnya', 'owner_name' => $budi->name, 'due_date' => now()->addDays(1)->format('Y-m-d')],
                    ['text' => 'Sari melakukan UAT modul kamar minggu depan', 'owner_name' => $sari->name, 'due_date' => now()->addDays(7)->format('Y-m-d')],
                ],
            ],
            [
                'project_id'   => $p4->id,
                'title'        => 'Kick-off E-Resep Integration - RSUD Bandung',
                'meeting_date' => now()->subDays(7)->format('Y-m-d'),
                'location'     => 'Zoom',
                'created_by'   => $pm->id,
                'attendees'    => [['name' => $pm->name], ['name' => $budi->name], ['name' => 'Apt. Dewi (Farmasi RSUD)'], ['name' => 'Ari (IT RSUD)']],
                'agenda'       => "1. Penjelasan scope project\n2. Analisis sistem farmasi existing\n3. Diskusi API BPJS\n4. Penyusunan timeline",
                'summary'      => 'Kick-off berjalan lancar. Sistem farmasi RSUD masih legacy, perlu middleware adapter. Timeline disepakati 3 bulan. API BPJS Farmasi akan dianalisis minggu ini oleh Budi.',
                'decisions'    => [
                    ['text' => 'Budi analisis dokumentasi API BPJS Farmasi dan buat laporan feasibility', 'owner_name' => $budi->name, 'due_date' => now()->subDays(3)->format('Y-m-d')],
                    ['text' => 'IT RSUD menyiapkan akses ke sistem farmasi existing untuk analisis', 'owner_name' => 'Ari (IT RSUD)', 'due_date' => now()->subDays(4)->format('Y-m-d')],
                    ['text' => 'PM menyiapkan dokumen SLA dan kontrak kerja', 'owner_name' => $pm->name, 'due_date' => now()->subDays(5)->format('Y-m-d')],
                ],
            ],
            [
                'project_id'   => null,
                'title'        => 'All-Hands Tim Teknologi — Review Bulanan',
                'meeting_date' => now()->subDays(10)->format('Y-m-d'),
                'location'     => 'Kantor - Ruang Rapat Lantai 3',
                'created_by'   => $users['admindemo']->id,
                'attendees'    => [['name' => $pm->name], ['name' => $budi->name], ['name' => $sari->name], ['name' => $rudi->name]],
                'agenda'       => "1. Update progress semua project\n2. Evaluasi performa tim\n3. Rencana bulan depan\n4. Q&A",
                'summary'      => 'SKI-001 dan SKI-004 on-track. SKI-002 berhasil go-live. Tim mengusulkan tooling baru untuk monitoring. Disepakati implementasi sistem absensi digital untuk tim lapangan.',
                'decisions'    => [
                    ['text' => 'Implementasi sistem absensi digital untuk tim lapangan mulai bulan depan', 'owner_name' => $users['admindemo']->name, 'due_date' => now()->addDays(20)->format('Y-m-d')],
                    ['text' => 'Setup monitoring dashboard Grafana untuk semua project aktif', 'owner_name' => $budi->name, 'due_date' => now()->addDays(14)->format('Y-m-d')],
                ],
            ],
        ];

        foreach ($minutesData as $data) {
            if (!Minute::where('title', $data['title'])->exists()) {
                Minute::create($data);
            }
        }

        $this->command->info('  ✓ Meeting Minutes (3 notulensi)');
    }
}
