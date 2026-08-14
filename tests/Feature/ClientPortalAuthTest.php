<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPortalAuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(array $overrides = []): Client
    {
        $withPassword = ! array_key_exists('password', $overrides);
        unset($overrides['password']);

        $client = Client::create(array_merge([
            'kode' => 'CLT-'.uniqid(),
            'nama' => 'Klien Test',
            'username' => 'klien_test',
            'email' => 'klien@test.com',
            'is_active' => true,
        ], $overrides));

        // password is intentionally not mass-assignable; set it directly so the
        // 'hashed' cast still applies (mirrors regenerateLoginPassword()).
        if ($withPassword) {
            $client->password = 'rahasia12345';
            $client->save();
        }

        return $client;
    }

    public function test_client_can_login_with_username(): void
    {
        $client = $this->makeClient();

        $response = $this->post('/portal/login', [
            'username' => 'klien_test',
            'password' => 'rahasia12345',
        ]);

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertTrue(auth('client')->check());
        $this->assertSame($client->id, auth('client')->id());
    }

    public function test_client_can_login_with_email_as_fallback(): void
    {
        $client = $this->makeClient();

        $response = $this->post('/portal/login', [
            'username' => 'klien@test.com',
            'password' => 'rahasia12345',
        ]);

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertSame($client->id, auth('client')->id());
    }

    public function test_client_cannot_login_with_wrong_password(): void
    {
        $this->makeClient();

        $response = $this->from('/portal/login')->post('/portal/login', [
            'username' => 'klien_test',
            'password' => 'salah-password',
        ]);

        $response->assertRedirect('/portal/login');
        $response->assertSessionHasErrors('username');
        $this->assertFalse(auth('client')->check());
    }

    public function test_guest_is_redirected_from_portal_dashboard_to_login(): void
    {
        $response = $this->get('/portal');

        $response->assertRedirect('/portal/login');
    }

    public function test_logged_in_client_does_not_gain_staff_access(): void
    {
        $this->makeClient();

        // Log in through the real portal endpoint so only the client guard's
        // session is established (not artificially via actingAs' shouldUse).
        $this->post('/portal/login', [
            'username' => 'klien_test',
            'password' => 'rahasia12345',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertTrue(auth('client')->check());
        $this->assertFalse(auth('web')->check());

        // Staff routes run on the web guard; a client session must not satisfy them.
        $this->get('/tickets')->assertRedirect(route('login'));
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $this->makeClient();

        // 5 allowed attempts per username+IP; the 6th must be throttled.
        for ($i = 0; $i < 5; $i++) {
            $this->from('/portal/login')->post('/portal/login', [
                'username' => 'klien_test',
                'password' => 'salah',
            ]);
        }

        $response = $this->from('/portal/login')->post('/portal/login', [
            'username' => 'klien_test',
            'password' => 'salah',
        ]);

        $response->assertSessionHasErrors('username');
        $errors = session('errors')->get('username');
        $this->assertStringContainsString('Terlalu banyak percobaan', $errors[0]);

        // Even the correct password is blocked while throttled.
        $this->post('/portal/login', [
            'username' => 'klien_test',
            'password' => 'rahasia12345',
        ]);
        $this->assertFalse(auth('client')->check());
    }

    public function test_regenerate_login_password_persists_a_usable_hash(): void
    {
        $client = $this->makeClient(['password' => null]);

        $plain = $client->regenerateLoginPassword();

        $this->assertNotEmpty($plain);
        $this->assertTrue(auth('client')->attempt([
            'email' => 'klien@test.com',
            'password' => $plain,
        ]));
    }
}
