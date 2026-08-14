<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientRequestQuota;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AiTicketCopilotService;
use App\Services\ExternalTicketNotifier;
use App\Services\TicketAttachmentStorage;
use App\Services\TicketNumberingService;
use App\Support\TicketTitle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Lets a logged-in client raise a ticket from the portal, mirroring the
 * Telegram intake flow (same numbering, status, staff notification). Kept
 * separate from ClientPortal\TicketController so that one stays read-only.
 *
 * Feature requests ("permintaan baru") consume the client's monthly quota;
 * bug reports are always free.
 */
class TicketSubmissionController extends Controller
{
    /** Categories a client may pick, mirroring the Telegram bot's labels. */
    private const ALLOWED_TYPES = ['bug', 'feature', 'task'];

    public function __construct(
        private readonly TicketNumberingService $ticketNumbering,
        private readonly ExternalTicketNotifier $notifier,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $client = $this->currentClient();

        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', self::ALLOWED_TYPES),
            'title' => 'nullable|string|max:255',
            'description' => 'required|string|max:5000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => TicketAttachmentStorage::validationRule(),
        ]);

        $type = $validated['type'];
        $description = trim($validated['description']);
        $title = trim((string) ($validated['title'] ?? ''));

        $reporter = $this->portalSystemUser();

        $ticket = DB::transaction(function () use ($client, $type, $description, $title, $reporter) {
            // Quota is spent inside the same transaction as the ticket insert,
            // so a failure here can never silently burn a request.
            if (ClientRequestQuota::typeConsumesQuota($type) && ! $client->isRequestUnlimited()) {
                if (ClientRequestQuota::consume($client) === null) {
                    throw ValidationException::withMessages([
                        'type' => 'Kuota permintaan baru Anda sudah habis. Silakan tunggu kuota bulan berikutnya atau hubungi tim kami.',
                    ]);
                }
            }

            $lockedClient = Client::lockForUpdate()->findOrFail($client->id);
            $ticketNumber = $this->ticketNumbering->nextTicketNumber($lockedClient, null);

            $ticket = Ticket::create([
                'client_id' => $lockedClient->id,
                'reporter_id' => $reporter->id,
                'title' => $title !== ''
                    ? Str::limit($title, 90)
                    : TicketTitle::fromDescription($description, 'Tiket Portal Klien'),
                'description' => $description,
                'ticket_number' => $ticketNumber,
                'source' => 'portal',
                'external_reporter_name' => $lockedClient->nama,
                'external_reporter_contact' => $lockedClient->email,
                'type' => $type,
                'priority' => 'medium',
                'status' => 'todo',
            ]);

            $ticket->logStatusChange('todo', null, $reporter->id);

            return $ticket;
        });

        $files = array_values(array_filter((array) $request->file('attachments')));
        if ($files !== []) {
            $ticket->update(['attachments' => TicketAttachmentStorage::store($ticket, $files)]);
        }

        $this->notifier->notifyStaff($ticket, $client->nama, $client->nama);

        return redirect()->back()->with('success', "Tiket {$ticket->ticket_number} berhasil dibuat.");
    }

    /**
     * Ask the AI copilot whether a similar issue was already solved before, so
     * the client can avoid raising a duplicate (and burning quota). Never
     * blocks submission: any failure degrades to "no match".
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:5000',
        ]);

        $empty = ['found' => false, 'ticket_number' => null, 'title' => null, 'suggestion' => null];

        try {
            $result = app(AiTicketCopilotService::class)
                ->findSimilarSolution($validated['description'], (int) Auth::guard('client')->id());
        } catch (Throwable $e) {
            Log::warning('Portal duplicate check failed', ['msg' => $e->getMessage()]);

            return response()->json($empty);
        }

        return response()->json(array_merge($empty, $result));
    }

    private function currentClient(): Client
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();

        return $client;
    }

    /**
     * Shared system user credited as reporter for portal-submitted tickets.
     * firstOrCreate so older environments that missed the seeding migration
     * still work.
     */
    private function portalSystemUser(): User
    {
        return User::firstOrCreate(
            ['username' => 'portal-bot'],
            [
                'name' => 'Portal Klien',
                'email' => 'portal-bot@system.local',
                'password' => Hash::make(Str::random(40)),
                'role_id' => null,
                'is_system' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
