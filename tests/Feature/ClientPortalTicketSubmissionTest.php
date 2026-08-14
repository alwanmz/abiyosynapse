<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientRequestQuota;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPortalTicketSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(string $kode = 'CLT', array $attributes = []): Client
    {
        return Client::create(array_merge([
            'kode' => $kode,
            'nama' => "Klien {$kode}",
            'email' => strtolower($kode).'@test.com',
            'is_active' => true,
        ], $attributes));
    }

    private function submit(Client $client, string $type = 'feature', string $description = 'Mohon tambahkan kolom baru di laporan kasir.')
    {
        return $this->actingAs($client, 'client')
            ->post('/portal/tickets', [
                'type' => $type,
                'description' => $description,
            ]);
    }

    public function test_client_can_submit_a_ticket_from_the_portal(): void
    {
        $client = $this->makeClient('RHK');

        $this->submit($client, 'bug', 'Menu kasir error saat cetak struk.')
            ->assertRedirect();

        $ticket = Ticket::firstOrFail();

        $this->assertSame($client->id, $ticket->client_id);
        $this->assertSame('portal', $ticket->source);
        $this->assertSame('todo', $ticket->status);
        $this->assertSame('bug', $ticket->type);
        $this->assertSame('RHK-0001', $ticket->ticket_number);
    }

    public function test_feature_requests_consume_quota_but_bugs_do_not(): void
    {
        config(['tickets.portal.monthly_request_quota' => 10]);
        $client = $this->makeClient();

        $this->submit($client, 'bug', 'Ada error di modul farmasi.');
        $this->assertNull(ClientRequestQuota::where('client_id', $client->id)->value('remaining'));

        $this->submit($client, 'feature');
        $this->assertSame(9, ClientRequestQuota::where('client_id', $client->id)->value('remaining'));

        $this->submit($client, 'task', 'Mohon dijadwalkan training ulang.');
        $this->assertSame(9, ClientRequestQuota::where('client_id', $client->id)->value('remaining'));
    }

    public function test_request_beyond_quota_is_rejected(): void
    {
        config(['tickets.portal.monthly_request_quota' => 2]);
        $client = $this->makeClient();

        $this->submit($client)->assertRedirect();
        $this->submit($client)->assertRedirect();

        $this->submit($client)->assertSessionHasErrors('type');

        $this->assertSame(2, Ticket::count());
        $this->assertSame(0, ClientRequestQuota::where('client_id', $client->id)->value('remaining'));
    }

    public function test_leftover_balance_carries_over_and_only_tops_up_once_spent(): void
    {
        config(['tickets.portal.monthly_request_quota' => 10]);
        $client = $this->makeClient();
        $lastMonth = now()->subMonth()->startOfMonth()->toDateString();

        // Leftover from last month must be burned first — no top-up.
        ClientRequestQuota::create([
            'client_id' => $client->id,
            'remaining' => 3,
            'topup_month' => $lastMonth,
        ]);
        $this->assertSame(3, ClientRequestQuota::remainingFor($client->fresh()));

        // Once it hits zero in a later month, the allowance is restored.
        ClientRequestQuota::where('client_id', $client->id)->update([
            'remaining' => 0,
            'topup_month' => $lastMonth,
        ]);
        $this->assertSame(10, ClientRequestQuota::remainingFor($client->fresh()));
    }

    public function test_exhausted_balance_is_not_topped_up_within_the_same_month(): void
    {
        config(['tickets.portal.monthly_request_quota' => 1]);
        $client = $this->makeClient();

        $this->submit($client)->assertRedirect();
        $this->submit($client)->assertSessionHasErrors('type');

        $this->assertSame(0, ClientRequestQuota::remainingFor($client->fresh()));
    }

    public function test_unlimited_client_is_never_capped(): void
    {
        config(['tickets.portal.monthly_request_quota' => 1]);
        $client = $this->makeClient('UNL', ['request_quota_unlimited' => true]);

        $this->submit($client)->assertRedirect();
        $this->submit($client)->assertRedirect();
        $this->submit($client)->assertRedirect();

        $this->assertSame(3, Ticket::count());
        $this->assertNull(ClientRequestQuota::remainingFor($client->fresh()));
        $this->assertDatabaseMissing('client_request_quotas', ['client_id' => $client->id]);
    }

    public function test_per_client_allowance_overrides_the_default(): void
    {
        config(['tickets.portal.monthly_request_quota' => 10]);
        $client = $this->makeClient('SML', ['monthly_request_quota' => 3]);

        $this->submit($client)->assertRedirect();
        $this->submit($client)->assertRedirect();
        $this->submit($client)->assertRedirect();
        $this->submit($client)->assertSessionHasErrors('type');

        $this->assertSame(3, Ticket::count());
    }

    public function test_quota_is_tracked_per_client(): void
    {
        config(['tickets.portal.monthly_request_quota' => 5]);
        $a = $this->makeClient('AAA');
        $b = $this->makeClient('BBB');

        $this->submit($a);

        $this->assertSame(4, ClientRequestQuota::remainingFor($a->fresh()));
        $this->assertSame(5, ClientRequestQuota::remainingFor($b->fresh()));
    }

    public function test_attachments_are_stored_on_the_ticket(): void
    {
        Storage::fake('public');
        $client = $this->makeClient();

        $this->actingAs($client, 'client')
            ->post('/portal/tickets', [
                'type' => 'bug',
                'description' => 'Terlampir screenshot errornya.',
                'attachments' => [UploadedFile::fake()->image('error.png')],
            ])->assertRedirect();

        $ticket = Ticket::firstOrFail();

        $this->assertCount(1, $ticket->attachments);
        Storage::disk('public')->assertExists($ticket->attachments[0]['path']);
    }

    public function test_guest_cannot_submit_a_ticket(): void
    {
        $this->post('/portal/tickets', [
            'type' => 'bug',
            'description' => 'Tanpa login.',
        ])->assertRedirect('/portal/login');

        $this->assertSame(0, Ticket::count());
    }
}
