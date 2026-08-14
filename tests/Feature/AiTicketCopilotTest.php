<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AiService;
use App\Services\AiTicketCopilotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AiTicketCopilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_copilot_service_finds_similar_solution_from_resolved_tickets(): void
    {
        $client = Client::create([
            'kode' => 'RSU-COPILOT',
            'nama' => 'RSU Copilot Test',
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        Ticket::create([
            'client_id' => $client->id,
            'reporter_id' => $user->id,
            'title' => 'Gagal bridging BPJS token expired',
            'description' => 'Gagal bridging BPJS error code 500 token kedaluwarsa',
            'review_notes' => 'Reset token di menu Pengaturan BPJS lalu sinkronkan ulang.',
            'ticket_number' => 'TCK-COPILOT-001',
            'priority' => 'high',
            'status' => 'resolved',
            'request_type' => 'gratis',
            'updated_at' => now(),
        ]);

        $this->mock(AiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->byDefault()->andReturn(true);
            $mock->shouldReceive('generateText')
                ->once()
                ->andReturn(json_encode([
                    'found' => true,
                    'ticket_number' => 'TCK-COPILOT-001',
                    'title' => 'Gagal bridging BPJS token expired',
                    'suggestion' => 'Masalah ini biasanya terjadi karena token BPJS kedaluwarsa. Silakan reset token di menu Pengaturan BPJS.',
                ]));
        });

        $copilot = app(AiTicketCopilotService::class);
        $result = $copilot->findSimilarSolution('Error bridging BPJS 500 token kedaluwarsa', $client->id);

        $this->assertTrue($result['found']);
        $this->assertEquals('TCK-COPILOT-001', $result['ticket_number']);
        $this->assertStringContainsString('token BPJS kedaluwarsa', $result['suggestion']);
    }

    public function test_api_check_similar_ticket_endpoint(): void
    {
        $adminRole = Role::create(['name' => 'super_admin', 'display_name' => 'Super Admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $this->mock(AiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('generateText')
                ->andReturn(json_encode(['found' => false]));
        });

        $response = $this->actingAs($admin)
            ->postJson('/ai/check-similar-ticket', [
                'description' => 'Printer kasir mati tidak bisa cetak nota',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['found' => false]);
    }
}
