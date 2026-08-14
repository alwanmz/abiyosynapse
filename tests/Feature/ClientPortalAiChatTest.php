<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientAiUsage;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ClientPortalAiChatTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(string $kode = 'CLT-A'): Client
    {
        return Client::create([
            'kode' => $kode,
            'nama' => "Klien {$kode}",
            'email' => strtolower($kode).'@test.com',
            'is_active' => true,
        ]);
    }

    private function makeTicket(Client $client, User $reporter, string $number): void
    {
        Ticket::create([
            'client_id' => $client->id,
            'reporter_id' => $reporter->id,
            'title' => "Tiket {$number}",
            'ticket_number' => $number,
            'priority' => 'medium',
            'status' => 'inprogress',
            'request_type' => 'gratis',
        ]);
    }

    private function fakeAi(?string $answer = 'Jawaban AI.'): void
    {
        $this->mock(AiService::class, function (MockInterface $mock) use ($answer) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('answerClientTicketQuestion')->andReturn($answer);
        });
    }

    public function test_client_can_ask_and_quota_decrements(): void
    {
        config(['services.deepseek.client_daily_limit' => 5]);
        $this->fakeAi('Tiket Anda sedang dikerjakan.');

        $reporter = User::factory()->create();
        $client = $this->makeClient();
        $this->makeTicket($client, $reporter, 'TCK-A-1');

        $response = $this->actingAs($client, 'client')
            ->postJson('/portal/ai/chat', ['question' => 'Status tiket saya?']);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('answer', 'Tiket Anda sedang dikerjakan.')
            ->assertJsonPath('remaining', 4);

        $this->assertSame(1, ClientAiUsage::todayCountFor($client->id));
    }

    public function test_quota_blocks_sixth_question_and_skips_ai(): void
    {
        config(['services.deepseek.client_daily_limit' => 5]);

        $client = $this->makeClient();

        // Pre-fill today's usage to the limit.
        ClientAiUsage::create([
            'client_id' => $client->id,
            'usage_date' => today(),
            'count' => 5,
        ]);

        // AI must NOT be invoked when the quota is exhausted.
        $this->mock(AiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('answerClientTicketQuestion')->never();
        });

        $response = $this->actingAs($client, 'client')
            ->postJson('/portal/ai/chat', ['question' => 'Halo?']);

        $response->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('remaining', 0);
    }

    public function test_quota_is_per_client(): void
    {
        config(['services.deepseek.client_daily_limit' => 5]);
        $this->fakeAi();

        $clientA = $this->makeClient('CLT-A');
        $clientB = $this->makeClient('CLT-B');

        $this->actingAs($clientA, 'client')
            ->postJson('/portal/ai/chat', ['question' => 'Tanya A?'])
            ->assertJsonPath('remaining', 4);

        // Client B still has a full quota.
        $this->actingAs($clientB, 'client')
            ->postJson('/portal/ai/chat', ['question' => 'Tanya B?'])
            ->assertJsonPath('remaining', 4);

        $this->assertSame(1, ClientAiUsage::todayCountFor($clientA->id));
        $this->assertSame(1, ClientAiUsage::todayCountFor($clientB->id));
    }

    public function test_guest_cannot_use_portal_ai(): void
    {
        $this->postJson('/portal/ai/chat', ['question' => 'Halo?'])
            ->assertUnauthorized();
    }
}
