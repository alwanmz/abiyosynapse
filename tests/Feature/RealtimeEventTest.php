<?php

namespace Tests\Feature;

use App\Events\DailyLogReactionToggled;
use App\Events\TicketStatusUpdated;
use App\Models\Client;
use App\Models\DailyLog;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_status_update_dispatches_ticket_status_updated_event(): void
    {
        Event::fake([TicketStatusUpdated::class]);

        $adminRole = Role::create(['name' => 'super_admin', 'display_name' => 'Super Admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $client = Client::create(['kode' => 'RSU-EVENT', 'nama' => 'RSU Event Test', 'is_active' => true]);

        $ticket = Ticket::create([
            'client_id' => $client->id,
            'reporter_id' => $admin->id,
            'assigned_to' => $admin->id,
            'title' => 'Tiket Event Test',
            'ticket_number' => 'TCK-EVT-001',
            'priority' => 'medium',
            'status' => 'todo',
            'request_type' => 'gratis',
        ]);

        $ticket->assignees()->attach($admin->id, ['role' => 'delegate']);

        $response = $this->actingAs($admin)
            ->put("/tickets/{$ticket->id}", [
                'status' => 'inprogress',
                'assigned_to' => $admin->id,
                'sync_assignees' => true,
                'assignees' => [$admin->id],
            ]);

        $response->assertSessionHasNoErrors();

        Event::assertDispatched(TicketStatusUpdated::class, function ($event) use ($ticket) {
            return $event->ticket->id === $ticket->id 
                && $event->fromStatus === 'todo' 
                && $event->toStatus === 'inprogress';
        });
    }

    public function test_daily_log_reaction_dispatches_daily_log_reaction_toggled_event(): void
    {
        Event::fake([DailyLogReactionToggled::class]);

        $adminRole = Role::create(['name' => 'super_admin', 'display_name' => 'Super Admin']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);

        $log = DailyLog::create([
            'user_id' => $user->id,
            'log_date' => now()->toDateString(),
            'log_number' => 'LOG-EVT-001',
            'category' => 'development',
            'description' => 'Selesai perbaikan event broadcast',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/daily-logs/{$log->id}/react", [
                'emoji' => '🔥',
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(DailyLogReactionToggled::class, function ($event) use ($log, $user) {
            return $event->dailyLog->id === $log->id 
                && $event->emoji === '🔥' 
                && $event->userId === $user->id 
                && $event->action === 'added';
        });
    }
}
