<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(string $kode): Client
    {
        return Client::create([
            'kode' => $kode,
            'nama' => "Klien {$kode}",
            'email' => strtolower($kode).'@test.com',
            'is_active' => true,
        ]);
    }

    private function makeTicket(Client $client, User $reporter, string $number, string $status): void
    {
        Ticket::create([
            'client_id' => $client->id,
            'reporter_id' => $reporter->id,
            'title' => "Tiket {$number}",
            'ticket_number' => $number,
            'priority' => 'medium',
            'status' => $status,
            'request_type' => 'gratis',
        ]);
    }

    public function test_dashboard_counts_only_the_logged_in_clients_tickets(): void
    {
        $reporter = User::factory()->create();
        $clientA = $this->makeClient('CLT-A');
        $clientB = $this->makeClient('CLT-B');

        $this->makeTicket($clientA, $reporter, 'TCK-A-1', 'inprogress');
        $this->makeTicket($clientA, $reporter, 'TCK-A-2', 'done');
        $this->makeTicket($clientB, $reporter, 'TCK-B-1', 'inprogress');

        $response = $this->actingAs($clientA, 'client')->get('/portal');

        $response->assertInertia(fn ($page) => $page
            ->component('client-portal/dashboard')
            ->where('stats.total', 2)
            ->where('stats.inprogress', 1)
            ->where('stats.done', 1));
    }

    public function test_guest_is_redirected_to_portal_login(): void
    {
        $this->get('/portal')->assertRedirect('/portal/login');
    }
}
