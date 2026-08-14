<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MaintenanceReport;
use App\Models\Project;
use App\Services\MaintenanceReportExportService;
use App\Services\MaintenanceReportNumberingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MaintenanceReportController extends Controller
{
    public function __construct(
        private MaintenanceReportNumberingService $numbering,
        private MaintenanceReportExportService $exporter,
    ) {}

    public function index(Request $request): Response
    {
        $clientId = $request->get('client_id');
        $status = $request->get('status');

        $reports = MaintenanceReport::query()
            ->with(['client:id,kode,nama', 'project:id,name', 'creator:id,name'])
            ->withCount('items')
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('maintenance-reports/index', [
            'reports' => $reports,
            'clients' => Client::select('id', 'kode', 'nama')->where('is_active', true)->orderBy('nama')->get(),
            'filters' => ['client_id' => $clientId, 'status' => $status],
            'can' => ['manage' => $this->canManage()],
        ]);
    }

    public function create(): Response
    {
        $this->authorizeManage();

        return Inertia::render('maintenance-reports/create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $this->validatePayload($request);

        $report = DB::transaction(function () use ($validated, $request) {
            $client = Client::findOrFail($validated['client_id']);
            $letterDate = $validated['letter_date'] ?? now()->toDateString();

            $report = MaintenanceReport::create([
                'client_id' => $validated['client_id'],
                'project_id' => $validated['project_id'] ?? null,
                'created_by' => Auth::id(),
                'report_number' => $this->numbering->nextReportNumber(Carbon::parse($validated['period_start'])),
                'letter_number' => $this->numbering->nextLetterNumber($client->kode ?: 'GEN', Carbon::parse($letterDate)),
                'letter_date' => $letterDate,
                'recipient_name' => $validated['recipient_name'] ?? null,
                'recipient_title' => $validated['recipient_title'] ?? null,
                'recipient_address' => $validated['recipient_address'] ?? null,
                'title' => $validated['title'],
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'summary' => $validated['summary'] ?? null,
                'signed_by_name' => $validated['signed_by_name'] ?? null,
                'signed_by_role' => $validated['signed_by_role'] ?? null,
                'status' => 'draft',
            ]);

            $this->syncItems($report, $validated['items'] ?? []);
            $this->storeSignature($request, $report);

            return $report;
        });

        return redirect()->route('maintenance-reports.edit', $report)
            ->with('success', 'Laporan maintenance berhasil dibuat.');
    }

    public function edit(MaintenanceReport $maintenanceReport): Response
    {
        $this->authorizeManage();

        $maintenanceReport->load(['items', 'client:id,kode,nama,alamat,director_name,director_title', 'project:id,name', 'creator:id,name']);

        return Inertia::render('maintenance-reports/edit', array_merge($this->formOptions(), [
            'report' => $this->detailPayload($maintenanceReport),
        ]));
    }

    public function update(Request $request, MaintenanceReport $maintenanceReport): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($validated, $request, $maintenanceReport) {
            $maintenanceReport->update([
                'client_id' => $validated['client_id'],
                'project_id' => $validated['project_id'] ?? null,
                'letter_date' => $validated['letter_date'] ?? $maintenanceReport->letter_date,
                'recipient_name' => $validated['recipient_name'] ?? null,
                'recipient_title' => $validated['recipient_title'] ?? null,
                'recipient_address' => $validated['recipient_address'] ?? null,
                'title' => $validated['title'],
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'summary' => $validated['summary'] ?? null,
                'signed_by_name' => $validated['signed_by_name'] ?? null,
                'signed_by_role' => $validated['signed_by_role'] ?? null,
            ]);

            $this->syncItems($maintenanceReport, $validated['items'] ?? []);
            $this->storeSignature($request, $maintenanceReport);
        });

        return redirect()->route('maintenance-reports.edit', $maintenanceReport)
            ->with('success', 'Laporan maintenance berhasil diperbarui.');
    }

    /**
     * Publishing is what makes the report visible (and downloadable) in the
     * client portal, so it is a deliberate separate action.
     */
    public function publish(MaintenanceReport $maintenanceReport): RedirectResponse
    {
        $this->authorizeManage();

        if ($maintenanceReport->items()->doesntExist()) {
            return redirect()->back()->withErrors([
                'status' => 'Laporan belum memiliki item pekerjaan, tidak dapat diterbitkan.',
            ]);
        }

        $maintenanceReport->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Laporan diterbitkan dan kini tampil di portal klien.');
    }

    public function unpublish(MaintenanceReport $maintenanceReport): RedirectResponse
    {
        $this->authorizeManage();

        $maintenanceReport->update(['status' => 'draft', 'published_at' => null]);

        return redirect()->back()->with('success', 'Laporan dikembalikan ke draft.');
    }

    /**
     * Formal Word document with the company letterhead — staff only.
     */
    public function exportDocx(MaintenanceReport $maintenanceReport): BinaryFileResponse
    {
        $path = $this->exporter->buildDocx($maintenanceReport);

        return response()
            ->download($path, $maintenanceReport->exportFilename('docx'))
            ->deleteFileAfterSend();
    }

    public function destroy(MaintenanceReport $maintenanceReport): RedirectResponse
    {
        $this->authorizeManage();

        if ($maintenanceReport->signature_path) {
            Storage::disk('public')->delete($maintenanceReport->signature_path);
        }

        $maintenanceReport->delete();

        return redirect()->route('maintenance-reports.index')
            ->with('success', 'Laporan maintenance berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'title' => 'required|string|max:255',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'letter_date' => 'nullable|date',
            'recipient_name' => 'nullable|string|max:150',
            'recipient_title' => 'nullable|string|max:150',
            'recipient_address' => 'nullable|string|max:2000',
            'summary' => 'nullable|string|max:10000',
            'signed_by_name' => 'nullable|string|max:150',
            'signed_by_role' => 'nullable|string|max:150',
            'signature' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'items' => 'nullable|array|max:200',
            'items.*.ticket_id' => 'nullable|integer|exists:tickets,id',
            'items.*.category' => 'nullable|string|max:100',
            'items.*.found_at' => 'nullable|date',
            'items.*.description' => 'required|string|max:2000',
            'items.*.resolution' => 'nullable|string|max:2000',
            'items.*.status_result' => 'nullable|string|max:100',
            'items.*.resolved_at' => 'nullable|date',
            'items.*.notes' => 'nullable|string|max:2000',
        ]);
    }

    /**
     * Items are fully replaced on each save — the form always submits the
     * complete list, so this keeps ordering and deletions trivial.
     */
    private function syncItems(MaintenanceReport $report, array $items): void
    {
        $report->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $report->items()->create([
                'ticket_id' => $item['ticket_id'] ?? null,
                'category' => $item['category'] ?? null,
                'found_at' => $item['found_at'] ?? null,
                'description' => $item['description'],
                'resolution' => $item['resolution'] ?? null,
                'status_result' => $item['status_result'] ?? null,
                'resolved_at' => $item['resolved_at'] ?? null,
                'notes' => $item['notes'] ?? null,
                'order' => $index,
            ]);
        }
    }

    private function storeSignature(Request $request, MaintenanceReport $report): void
    {
        if (! $request->hasFile('signature')) {
            return;
        }

        if ($report->signature_path) {
            Storage::disk('public')->delete($report->signature_path);
        }

        $report->update([
            'signature_path' => $request->file('signature')->store('maintenance-reports/signatures', 'public'),
        ]);
    }

    private function formOptions(): array
    {
        return [
            'clients' => Client::select('id', 'kode', 'nama', 'alamat', 'director_name', 'director_title')
                ->where('is_active', true)->orderBy('nama')->get(),
            'projects' => Project::select('id', 'name', 'client_id')->orderBy('name')->get(),
            'statusResultOptions' => ['Berhasil', 'Sebagian', 'Gagal', 'Perlu Tindak Lanjut'],
        ];
    }

    private function detailPayload(MaintenanceReport $report): array
    {
        return [
            'id' => $report->id,
            'report_number' => $report->report_number,
            'letter_number' => $report->letter_number,
            'letter_date' => $report->letter_date?->toDateString(),
            'recipient_name' => $report->recipient_name,
            'recipient_title' => $report->recipient_title,
            'recipient_address' => $report->recipient_address,
            'title' => $report->title,
            'client_id' => $report->client_id,
            'project_id' => $report->project_id,
            'period_start' => $report->period_start?->toDateString(),
            'period_end' => $report->period_end?->toDateString(),
            'summary' => $report->summary,
            'signed_by_name' => $report->signed_by_name,
            'signed_by_role' => $report->signed_by_role,
            'signature_url' => $report->signature_path
                ? Storage::disk('public')->url($report->signature_path)
                : null,
            'status' => $report->status,
            'published_at' => $report->published_at,
            'client' => $report->client,
            'project' => $report->project,
            'creator' => $report->creator,
            'items' => $report->items->map(fn ($item) => [
                'id' => $item->id,
                'ticket_id' => $item->ticket_id,
                'category' => $item->category,
                'found_at' => $item->found_at?->toDateString(),
                'description' => $item->description,
                'resolution' => $item->resolution,
                'status_result' => $item->status_result,
                'resolved_at' => $item->resolved_at?->toDateString(),
                'notes' => $item->notes,
            ])->values(),
        ];
    }

    private function canManage(): bool
    {
        $user = Auth::user();

        return $user && ($user->isAdmin() || $user->hasPermissionTo('maintenance-reports.manage'));
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403);
    }
}
