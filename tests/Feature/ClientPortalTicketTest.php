<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\ClientCommentedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientPortalTicketTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(string $kode = 'CLT-A'): Client
    {
        $client = Client::create([
            'kode' => $kode,
            'nama' => "Klien {$kode}",
            'email' => strtolower($kode).'@test.com',
            'is_active' => true,
        ]);

        $client->password = 'rahasia12345';
        $client->save();

        return $client;
    }

    private function makeTicket(Client $client, User $reporter, string $number): Ticket
    {
        return Ticket::create([
            'client_id' => $client->id,
            'reporter_id' => $reporter->id,
            'title' => "Tiket {$number}",
            'description' => 'Deskripsi kendala',
            'ticket_number' => $number,
            'priority' => 'medium',
            'status' => 'inprogress',
            'request_type' => 'gratis',
        ]);
    }

    public function test_client_only_sees_their_own_tickets(): void
    {
        $reporter = User::factory()->create();
        $clientA = $this->makeClient('CLT-A');
        $clientB = $this->makeClient('CLT-B');

        $this->makeTicket($clientA, $reporter, 'TCK-A-1');
        $this->makeTicket($clientB, $reporter, 'TCK-B-1');

        $response = $this->actingAs($clientA, 'client')->get('/portal/tickets');

        $response->assertInertia(fn ($page) => $page
            ->component('client-portal/tickets/index')
            ->has('tickets', 1)
            ->where('tickets.0.ticket_number', 'TCK-A-1'));
    }

    public function test_client_cannot_view_another_clients_ticket(): void
    {
        $reporter = User::factory()->create();
        $clientA = $this->makeClient('CLT-A');
        $clientB = $this->makeClient('CLT-B');

        $ticketB = $this->makeTicket($clientB, $reporter, 'TCK-B-1');

        $this->actingAs($clientA, 'client')
            ->get("/portal/tickets/{$ticketB->id}")
            ->assertNotFound();
    }

    public function test_internal_comments_are_never_exposed_to_client(): void
    {
        $reporter = User::factory()->create();
        $client = $this->makeClient('CLT-A');
        $ticket = $this->makeTicket($client, $reporter, 'TCK-A-1');

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'comment' => 'Catatan internal rahasia',
            'type' => 'comment',
            'is_internal' => true,
        ]);
        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'comment' => 'Balasan publik untuk klien',
            'type' => 'comment',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($client, 'client')->get("/portal/tickets/{$ticket->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('client-portal/tickets/show')
            ->has('ticket.comments', 1)
            ->where('ticket.comments.0.comment', 'Balasan publik untuk klien'));
    }

    public function test_client_can_post_comment_and_staff_is_notified(): void
    {
        Notification::fake();

        $reporter = User::factory()->create();
        $client = $this->makeClient('CLT-A');
        $ticket = $this->makeTicket($client, $reporter, 'TCK-A-1');

        $this->actingAs($client, 'client')
            ->post("/portal/tickets/{$ticket->id}/comments", [
                'comment' => 'Kapan estimasi selesai?',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'client_id' => $client->id,
            'user_id' => null,
            'is_internal' => false,
            'comment' => 'Kapan estimasi selesai?',
        ]);

        Notification::assertSentTo($reporter, ClientCommentedNotification::class);
    }

    public function test_detail_json_returns_attachments_and_public_comments_only(): void
    {
        $reporter = User::factory()->create();
        $client = $this->makeClient('CLT-A');
        $ticket = $this->makeTicket($client, $reporter, 'TCK-A-1');
        $ticket->update([
            'attachments' => [[
                'name' => 'bukti.png',
                'path' => 'ticket-attachments/'.$ticket->id.'/bukti.png',
                'disk' => 'public',
                'url' => '/storage/ticket-attachments/'.$ticket->id.'/bukti.png',
                'size' => 1234,
                'mime_type' => 'image/png',
            ]],
        ]);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'comment' => 'Rahasia internal',
            'type' => 'comment',
            'is_internal' => true,
        ]);

        $response = $this->actingAs($client, 'client')
            ->getJson("/portal/tickets/{$ticket->id}/detail");

        $response->assertOk()
            ->assertJsonPath('ticket.attachments.0.name', 'bukti.png')
            ->assertJsonPath('ticket.comments', []);

        // Attachment URL must point at the ownership-checked portal route.
        $this->assertStringContainsString(
            "/portal/tickets/{$ticket->id}/attachment",
            $response->json('ticket.attachments.0.url'),
        );
    }

    public function test_detail_of_another_clients_ticket_is_forbidden(): void
    {
        $reporter = User::factory()->create();
        $clientA = $this->makeClient('CLT-A');
        $clientB = $this->makeClient('CLT-B');
        $ticketB = $this->makeTicket($clientB, $reporter, 'TCK-B-1');

        $this->actingAs($clientA, 'client')
            ->getJson("/portal/tickets/{$ticketB->id}/detail")
            ->assertNotFound();
    }

    public function test_attachment_of_another_clients_ticket_is_forbidden(): void
    {
        $reporter = User::factory()->create();
        $clientA = $this->makeClient('CLT-A');
        $clientB = $this->makeClient('CLT-B');
        $ticketB = $this->makeTicket($clientB, $reporter, 'TCK-B-1');

        $this->actingAs($clientA, 'client')
            ->get("/portal/tickets/{$ticketB->id}/attachment?filename=anything.png")
            ->assertNotFound();
    }

    public function test_board_only_lists_own_tickets(): void
    {
        $reporter = User::factory()->create();
        $clientA = $this->makeClient('CLT-A');
        $clientB = $this->makeClient('CLT-B');
        $ticketA = $this->makeTicket($clientA, $reporter, 'TCK-A-1');
        $this->makeTicket($clientB, $reporter, 'TCK-B-1');

        TicketComment::create([
            'ticket_id' => $ticketA->id,
            'user_id' => $reporter->id,
            'comment' => 'Komentar publik',
            'is_internal' => false,
        ]);

        $this->actingAs($clientA, 'client')->get('/portal/kanban')
            ->assertInertia(fn ($page) => $page
                ->component('client-portal/tickets/board')
                ->has('tickets', 1)
                ->where('tickets.0.ticket_number', 'TCK-A-1')
                ->where('tickets.0.comments_count', 1));
    }
}
