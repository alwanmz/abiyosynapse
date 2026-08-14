<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MaintenanceReport;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'programmer'], ['display_name' => 'Programmer']);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);
    }

    private function client(string $kode = 'RSU-A', string $nama = 'RSU Alpha'): Client
    {
        return Client::create([
            'kode' => $kode,
            'nama' => $nama,
            'is_active' => true,
        ]);
    }

    private function reportPayload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $client->id,
            'title' => 'Laporan Pemeliharaan Januari 2026',
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'summary' => 'Pemeliharaan rutin berjalan lancar.',
            'signed_by_name' => 'Budi Santoso',
            'signed_by_role' => 'Project Manager',
            'items' => [
                [
                    'category' => 'Database',
                    'description' => 'Optimasi indeks tabel transaksi',
                    'status_result' => 'Berhasil',
                    'notes' => 'Waktu query turun 40%',
                ],
                [
                    'category' => 'Server',
                    'description' => 'Pembaruan patch keamanan',
                    'status_result' => 'Berhasil',
                ],
            ],
        ], $overrides);
    }

    public function test_staff_can_create_report_with_items_and_generated_number(): void
    {
        $client = $this->client();

        $this->actingAs($this->admin())
            ->post(route('maintenance-reports.store'), $this->reportPayload($client))
            ->assertRedirect();

        $report = MaintenanceReport::firstOrFail();

        $this->assertSame('MR-202601-0001', $report->report_number);
        $this->assertSame('draft', $report->status);
        $this->assertCount(2, $report->items);
        $this->assertSame('Optimasi indeks tabel transaksi', $report->items[0]->description);
    }

    public function test_report_numbers_increment_within_the_same_month(): void
    {
        $client = $this->client();
        $admin = $this->admin();

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($admin)
                ->post(route('maintenance-reports.store'), $this->reportPayload($client));
        }

        $this->assertDatabaseHas('maintenance_reports', ['report_number' => 'MR-202601-0001']);
        $this->assertDatabaseHas('maintenance_reports', ['report_number' => 'MR-202601-0002']);
    }

    public function test_period_end_must_not_precede_period_start(): void
    {
        $client = $this->client();

        $this->actingAs($this->admin())
            ->post(route('maintenance-reports.store'), $this->reportPayload($client, [
                'period_start' => '2026-01-31',
                'period_end' => '2026-01-01',
            ]))
            ->assertSessionHasErrors('period_end');
    }

    public function test_update_replaces_items(): void
    {
        $client = $this->client();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('maintenance-reports.store'), $this->reportPayload($client));

        $report = MaintenanceReport::firstOrFail();

        $this->actingAs($admin)
            ->put(route('maintenance-reports.update', $report), $this->reportPayload($client, [
                'items' => [
                    ['description' => 'Satu-satunya pekerjaan tersisa', 'status_result' => 'Berhasil'],
                ],
            ]))
            ->assertRedirect();

        $report->refresh();

        $this->assertCount(1, $report->items);
        $this->assertSame('Satu-satunya pekerjaan tersisa', $report->items[0]->description);
    }

    public function test_signature_upload_is_stored(): void
    {
        Storage::fake('public');
        $client = $this->client();

        $this->actingAs($this->admin())
            ->post(route('maintenance-reports.store'), $this->reportPayload($client, [
                'signature' => UploadedFile::fake()->image('ttd.png'),
            ]))
            ->assertRedirect();

        $report = MaintenanceReport::firstOrFail();

        $this->assertNotNull($report->signature_path);
        Storage::disk('public')->assertExists($report->signature_path);
    }

    public function test_staff_can_download_docx_export(): void
    {
        $client = $this->client();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('maintenance-reports.store'), $this->reportPayload($client));

        $report = MaintenanceReport::firstOrFail();

        $response = $this->actingAs($admin)
            ->get(route('maintenance-reports.export.docx', $report));

        $response->assertOk();
        $response->assertDownload($report->exportFilename('docx'));
    }

    public function test_report_without_items_cannot_be_published(): void
    {
        $client = $this->client();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('maintenance-reports.store'), $this->reportPayload($client, ['items' => []]));

        $report = MaintenanceReport::firstOrFail();

        $this->actingAs($admin)
            ->post(route('maintenance-reports.publish', $report))
            ->assertSessionHasErrors('status');

        $this->assertSame('draft', $report->fresh()->status);
    }

    public function test_publishing_makes_the_report_visible_in_the_client_portal(): void
    {
        $client = $this->client();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('maintenance-reports.store'), $this->reportPayload($client));

        $report = MaintenanceReport::firstOrFail();

        // Sebelum terbit: tidak boleh bisa diunduh klien.
        $this->actingAs($client, 'client')
            ->get(route('portal.maintenance-reports.export', $report))
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('maintenance-reports.publish', $report))
            ->assertRedirect();

        $report->refresh();
        $this->assertSame('published', $report->status);
        $this->assertNotNull($report->published_at);

        $this->actingAs($client, 'client')
            ->get(route('portal.maintenance-reports'))
            ->assertOk();

        $this->actingAs($client, 'client')
            ->get(route('portal.maintenance-reports.export', $report))
            ->assertOk();
    }

    public function test_client_cannot_download_another_clients_report(): void
    {
        $owner = $this->client('RSU-A', 'RSU Alpha');
        $intruder = $this->client('RSU-B', 'RSU Beta');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('maintenance-reports.store'), $this->reportPayload($owner));

        $report = MaintenanceReport::firstOrFail();
        $this->actingAs($admin)->post(route('maintenance-reports.publish', $report));

        $this->actingAs($intruder, 'client')
            ->get(route('portal.maintenance-reports.export', $report))
            ->assertNotFound();
    }

    public function test_client_portal_list_only_shows_own_published_reports(): void
    {
        $owner = $this->client('RSU-A', 'RSU Alpha');
        $other = $this->client('RSU-B', 'RSU Beta');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('maintenance-reports.store'), $this->reportPayload($owner));
        $ownReport = MaintenanceReport::firstOrFail();
        $this->actingAs($admin)->post(route('maintenance-reports.publish', $ownReport));

        $this->actingAs($admin)
            ->post(route('maintenance-reports.store'), $this->reportPayload($other, [
                'title' => 'Laporan Milik Klien Lain',
            ]));

        $this->actingAs($owner, 'client')
            ->get(route('portal.maintenance-reports'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('client-portal/maintenance-reports/index')
                ->has('reports', 1)
                ->where('reports.0.report_number', $ownReport->report_number));
    }

    public function test_user_without_manage_permission_cannot_create_report(): void
    {
        $client = $this->client();
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'programmer')->first()->id,
        ]);

        $this->actingAs($user)
            ->post(route('maintenance-reports.store'), $this->reportPayload($client))
            ->assertForbidden();

        $this->assertDatabaseCount('maintenance_reports', 0);
    }
}
