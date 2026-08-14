<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketAttachmentDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Client $client;
    protected Project $project;
    protected Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $team = Team::create(['name' => 'Core Team']);
        $this->client = Client::create([
            'kode' => 'RHK',
            'nama' => 'Rumah Sakit Harapan Keluarga',
            'email' => 'rhk@test.com',
            'is_active' => true,
        ]);
        $this->client->password = 'password';
        $this->client->save();

        $this->project = Project::create([
            'name' => 'Project RHK',
            'status' => 'active',
            'team_id' => $team->id,
            'client_id' => $this->client->id,
            'project_manager_id' => $this->user->id,
        ]);

        Storage::fake('public');
        Storage::disk('public')->put('ticket-attachments/sample1.png', 'fake image content');
        Storage::disk('public')->put('ticket-attachments/sample2.png', 'fake image content 2');

        $this->ticket = Ticket::create([
            'ticket_number' => 'RHK-0001',
            'title' => 'Uji Coba Tiket Lampiran',
            'description' => 'Deskripsi kendala',
            'status' => 'backlog',
            'priority' => 'medium',
            'type' => 'bug',
            'project_id' => $this->project->id,
            'client_id' => $this->client->id,
            'reporter_id' => $this->user->id,
            'attachments' => [
                [
                    'name' => 'sample1.png',
                    'path' => 'ticket-attachments/sample1.png',
                    'disk' => 'public',
                    'size' => 1024,
                    'mime_type' => 'image/png',
                ],
                [
                    'name' => 'sample2.png',
                    'path' => 'ticket-attachments/sample2.png',
                    'disk' => 'public',
                    'size' => 2048,
                    'mime_type' => 'image/png',
                ],
            ],
        ]);

        $role = \App\Models\Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $permission = \App\Models\Permission::create(['name' => 'tickets.view', 'display_name' => 'View Tickets']);
        $role->permissions()->attach($permission);
        $this->user->role_id = $role->id;
        $this->user->save();
    }

    public function test_staff_can_delete_ticket_attachment(): void
    {
        $response = $this->actingAs($this->user)
            ->delete(route('tickets.attachments.destroy', $this->ticket), [
                'index' => 0,
                'filename' => 'sample1.png',
            ]);

        $response->assertRedirect();

        $this->ticket->refresh();
        $this->assertCount(1, $this->ticket->attachments);
        $this->assertEquals('sample2.png', $this->ticket->attachments[0]['name']);
        Storage::disk('public')->assertMissing('ticket-attachments/sample1.png');
    }

    public function test_client_portal_can_delete_ticket_attachment(): void
    {
        $response = $this->actingAs($this->client, 'client')
            ->delete(route('portal.tickets.attachments.destroy', $this->ticket), [
                'index' => 0,
                'filename' => 'sample1.png',
            ]);

        $response->assertRedirect();

        $this->ticket->refresh();
        $this->assertCount(1, $this->ticket->attachments);
        $this->assertEquals('sample2.png', $this->ticket->attachments[0]['name']);
        Storage::disk('public')->assertMissing('ticket-attachments/sample1.png');
    }
}
