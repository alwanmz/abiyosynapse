<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\DailyLog;
use App\Models\Minute;
use App\Models\Project;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoClearSeeder extends Seeder
{
    // Identitas unik data demo — hanya ini yang dihapus
    private array $demoEmails = [
        'admin@demo.ski',
        'pm@demo.ski',
        'budi@demo.ski',
        'sari@demo.ski',
        'rudi@demo.ski',
    ];

    private array $demoProjectCodes = ['SKI-001', 'SKI-002', 'SKI-003', 'SKI-004'];

    private array $demoClientCodes = ['RSUD-BDG', 'RS-HSN', 'PKM-DIG', 'RSIA-BDG'];

    private array $demoTeamNames = ['Tim Backend', 'Tim Lapangan'];


    public function run(): void
    {
        $this->command->info('🗑️  Menghapus data demo...');

        $demoUserIds  = User::whereIn('email', $this->demoEmails)->pluck('id');
        $demoProjectIds = Project::withTrashed()->whereIn('kode_project', $this->demoProjectCodes)->pluck('id');

        // Urutan hapus: child dulu sebelum parent
        $this->deleteMinutes($demoProjectIds, $demoUserIds);
        $this->deleteDailyLogs($demoUserIds);
        $this->deleteTickets($demoProjectIds);
        $this->deleteTimelines($demoProjectIds);
        $this->deleteProjects();
        $this->deleteClients();
        $this->deleteTeams($demoUserIds);
        $this->deleteUsers();

        $this->command->info('✅ Semua data demo berhasil dihapus.');
    }

    private function deleteMinutes($projectIds, $userIds): void
    {
        $deleted = Minute::where(function ($q) use ($projectIds, $userIds) {
            $q->whereIn('project_id', $projectIds)
              ->orWhereIn('created_by', $userIds);
        })->delete();
        $this->command->info("  ✓ Minutes dihapus: {$deleted}");
    }

    private function deleteDailyLogs($userIds): void
    {
        $deleted = DailyLog::whereIn('user_id', $userIds)->delete();
        $this->command->info("  ✓ Daily Logs dihapus: {$deleted}");
    }

    private function deleteTickets($projectIds): void
    {
        $deleted = Ticket::whereIn('project_id', $projectIds)->forceDelete();
        $this->command->info("  ✓ Tickets dihapus: {$deleted}");
    }

    private function deleteTimelines($projectIds): void
    {
        $deleted = \App\Models\ProjectTimeline::whereIn('project_id', $projectIds)->forceDelete();
        $this->command->info("  ✓ Timelines dihapus: {$deleted}");
    }

    private function deleteProjects(): void
    {
        $deleted = Project::withTrashed()->whereIn('kode_project', $this->demoProjectCodes)->forceDelete();
        $this->command->info("  ✓ Projects dihapus: {$deleted}");
    }

    private function deleteClients(): void
    {
        $deleted = Client::withTrashed()->whereIn('kode', $this->demoClientCodes)->forceDelete();
        $this->command->info("  ✓ Clients dihapus: {$deleted}");
    }

    private function deleteTeams($userIds): void
    {
        $teams = Team::whereIn('name', $this->demoTeamNames)->get();
        foreach ($teams as $team) {
            $team->users()->detach();
        }
        $deleted = Team::withTrashed()->whereIn('name', $this->demoTeamNames)->forceDelete();
        $this->command->info("  ✓ Teams dihapus: {$deleted}");
    }


    private function deleteUsers(): void
    {
        $deleted = User::whereIn('email', $this->demoEmails)->delete();
        $this->command->info("  ✓ Users dihapus: {$deleted}");
    }
}
