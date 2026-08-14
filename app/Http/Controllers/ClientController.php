<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientActionLog;
use App\Models\ClientRequestQuota;
use App\Models\TelegramContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::with(['requestQuota'])->with(['telegramContacts' => function ($q) {
            $q->whereNotNull('verified_at')->whereNull('revoked_at')->orderByDesc('verified_at');
        }]);

        if ($request->filled('search')) {
            $search = $request->search;
            // Postgres' LIKE is case-sensitive (unlike MySQL's default collation),
            // so "smt" would silently miss a client stored as "SMT". Use ILIKE
            // on pgsql to match case-insensitively either way.
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $operator) {
                $q->where('kode', $operator, "%{$search}%")
                    ->orWhere('nama', $operator, "%{$search}%")
                    ->orWhere('deskripsi', $operator, "%{$search}%");
            });
        }

        $clients = $query->orderBy('nama')->get()
            ->each(fn (Client $client) => $client
                ->append('has_portal_password')
                ->makeVisible('portal_password_plain'));

        return Inertia::render('master/clients/page', [
            'clients' => $clients,
            'filters' => ['search' => $request->search ?? ''],
            'defaultQuota' => (int) config('tickets.portal.monthly_request_quota', 10),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:clients,kode',
            'nama' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:1000',
            'director_name' => 'nullable|string|max:150',
            'director_title' => 'nullable|string|max:150',
            'kontak' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255|alpha_dash|unique:clients,username',
            'email' => 'nullable|email|max:255|unique:clients,email',
            'deskripsi' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
            // null = pakai default config('tickets.portal.monthly_request_quota')
            'monthly_request_quota' => 'nullable|integer|min:0|max:1000',
            'request_quota_unlimited' => 'boolean',
        ]);

        $client = Client::create($validated);

        $this->logAction($client->id, 'create', null, $client->toArray());

        return redirect()->back()->with('success', 'Client created successfully.');
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:clients,kode,' . $client->id,
            'nama' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:1000',
            'director_name' => 'nullable|string|max:150',
            'director_title' => 'nullable|string|max:150',
            'kontak' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255|alpha_dash|unique:clients,username,' . $client->id,
            'email' => 'nullable|email|max:255|unique:clients,email,' . $client->id,
            'deskripsi' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
            // null = pakai default config('tickets.portal.monthly_request_quota')
            'monthly_request_quota' => 'nullable|integer|min:0|max:1000',
            'request_quota_unlimited' => 'boolean',
        ]);

        $oldData = $client->toArray();
        $client->update($validated);

        $this->logAction($client->id, 'update', $oldData, $client->fresh()->toArray());

        return redirect()->back()->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client)
    {
        $oldData = $client->toArray();

        $this->logAction($client->id, 'delete', $oldData, null);

        $client->delete();

        return redirect()->back()->with('success', 'Client deleted successfully.');
    }

    /**
     * Generate (or regenerate) the PIN a client's Telegram contacts use to
     * verify themselves via the ticket-intake bot. Staff hand this out
     * out-of-band (WA/email) — regenerating invalidates the old code but
     * does NOT revoke already-verified contacts.
     */
    public function regenerateTelegramCode(Client $client)
    {
        $code = $client->regenerateTelegramVerificationCode();

        $this->logAction($client->id, 'regenerate_telegram_code', null, ['telegram_verification_code' => $code]);

        return redirect()->back()->with('success', "Kode verifikasi Telegram baru: {$code}");
    }

    /**
     * Generate (or regenerate) the password a client uses to log into the web
     * portal. Staff hand the plaintext out-of-band; it is shown once and only
     * its hash is stored. Requires the client to have an email set first.
     */
    public function regeneratePortalPassword(Client $client)
    {
        if (! $client->username && ! $client->email) {
            return redirect()->back()->with('error', 'Isi username (atau email) klien terlebih dahulu sebelum membuat password portal.');
        }

        $client->regenerateLoginPassword();

        $this->logAction($client->id, 'regenerate_portal_password', null, ['portal_password_set' => true]);

        return redirect()->back()->with('success', "Password portal untuk {$client->nama} berhasil dibuat.");
    }

    /**
     * Revoke a verified Telegram contact so they can no longer submit
     * tickets on behalf of this client until re-verified with a new code.
     */
    public function revokeTelegramContact(TelegramContact $telegramContact)
    {
        $telegramContact->update(['revoked_at' => now()]);

        $this->logAction($telegramContact->client_id, 'revoke_telegram_contact', null, [
            'chat_id' => $telegramContact->chat_id,
            'telegram_username' => $telegramContact->telegram_username,
        ]);

        return redirect()->back()->with('success', 'Kontak Telegram dicabut.');
    }

    /**
     * Refill a client's request balance to their current allowance right away.
     * Normally the balance only tops up once it hits zero in a later month, so
     * this is the manual escape hatch after staff change the allowance.
     */
    public function resetRequestQuota(Client $client)
    {
        if ($client->isRequestUnlimited()) {
            return redirect()->back()->with('error', 'Klien ini unlimited request, tidak perlu reset kuota.');
        }

        $remaining = ClientRequestQuota::resetFor($client);

        $this->logAction($client->id, 'reset_request_quota', null, ['remaining' => $remaining]);

        return redirect()->back()->with('success', "Kuota request {$client->nama} direset ke {$remaining}.");
    }

    /**
     * Persist every client mutation to the audit log table.
     * Mockup requirement: "SEMUA Aksi harus disimpan di table log".
     */
    private function logAction(?int $clientId, string $action, ?array $oldData, ?array $newData): void
    {
        ClientActionLog::create([
            'client_id' => $clientId,
            'user_id' => Auth::id(),
            'action' => $action,
            'old_data' => $oldData,
            'new_data' => $newData,
            'created_at' => now(),
        ]);
    }
}
