<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketCommentFeatureTest extends TestCase
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
        $team = \App\Models\Team::create(['name' => 'Core Team']);
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

        $this->ticket = Ticket::create([
            'ticket_number' => 'RHK-0001',
            'title' => 'Uji Coba Tiket',
            'description' => 'Deskripsi kendala',
            'status' => 'backlog',
            'priority' => 'medium',
            'type' => 'bug',
            'project_id' => $this->project->id,
            'client_id' => $this->client->id,
            'reporter_id' => $this->user->id,
        ]);
        $role = \App\Models\Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $permission = \App\Models\Permission::create(['name' => 'tickets.view', 'display_name' => 'View Tickets']);
        $role->permissions()->attach($permission);
        $this->user->role_id = $role->id;
        $this->user->save();
    }

    public function test_staff_can_post_comment_with_attachments(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('screen.png', 100, 'image/png');

        $response = $this->actingAs($this->user)
            ->post(route('tickets.comments.store', $this->ticket), [
                'comment' => 'Ini komentar dengan attachment @' . $this->user->name,
                'attachments' => [$file],
            ]);

        $response->assertRedirect();

        $comment = TicketComment::where('ticket_id', $this->ticket->id)->first();
        $this->assertNotNull($comment);
        $this->assertEquals('Ini komentar dengan attachment @' . $this->user->name, $comment->comment);
        $this->assertIsArray($comment->attachments);
        $this->assertCount(1, $comment->attachments);
    }

    public function test_client_portal_can_post_comment_with_attachments(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('laporan.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->client, 'client')
            ->post(route('portal.tickets.comments.store', $this->ticket), [
                'comment' => 'Komentar balasan dari portal klien',
                'attachments' => [$file],
            ]);

        $response->assertRedirect();

        $comment = TicketComment::where('ticket_id', $this->ticket->id)->first();
        $this->assertNotNull($comment);
        $this->assertTrue($comment->isFromClient());
        $this->assertCount(1, $comment->attachments);
    }

    public function test_staff_can_toggle_emoji_reaction_on_comment(): void
    {
        $comment = TicketComment::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->user->id,
            'comment' => 'Komentar untuk reaksi',
        ]);

        // Add reaction
        $response = $this->actingAs($this->user)
            ->post(route('tickets.comments.react', $comment), [
                'emoji' => '👍',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ticket_comment_reactions', [
            'ticket_comment_id' => $comment->id,
            'user_id' => $this->user->id,
            'emoji' => '👍',
        ]);

        // Remove reaction (toggle)
        $response2 = $this->actingAs($this->user)
            ->post(route('tickets.comments.react', $comment), [
                'emoji' => '👍',
            ]);

        $response2->assertRedirect();
        $this->assertDatabaseMissing('ticket_comment_reactions', [
            'ticket_comment_id' => $comment->id,
            'user_id' => $this->user->id,
            'emoji' => '👍',
        ]);
    }

    public function test_client_can_toggle_emoji_reaction_on_comment(): void
    {
        $comment = TicketComment::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->user->id,
            'comment' => 'Komentar dari staf',
        ]);

        $response = $this->actingAs($this->client, 'client')
            ->post(route('portal.tickets.comments.react', $comment), [
                'emoji' => '❤️',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ticket_comment_reactions', [
            'ticket_comment_id' => $comment->id,
            'client_id' => $this->client->id,
            'emoji' => '❤️',
        ]);
    }
}
