<?php

namespace App\Http\Controllers;

use App\Models\Guidebook;
use App\Models\GuidebookCategory;
use App\Services\GuidebookAttachmentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuidebookController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));
        $categoryId = $request->get('category_id');
        $contentType = $request->get('content_type');

        $guidebooks = Guidebook::query()
            ->with(['category:id,name,slug,icon,color', 'creator:id,name,avatar_path'])
            ->when($search, fn ($q) => $q->where(fn ($sub) => $sub
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($contentType, fn ($q) => $q->where('content_type', $contentType))
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Guidebook $guidebook) => $this->summaryPayload($guidebook));

        return Inertia::render('guidebooks/index', [
            'guidebooks' => $guidebooks,
            'categories' => GuidebookCategory::withCount('guidebooks')
                ->orderBy('order')
                ->orderBy('name')
                ->get(),
            'filters' => [
                'search' => $search ?: null,
                'category_id' => $categoryId,
                'content_type' => $contentType,
            ],
            'can' => [
                'manage' => $this->canManage(),
            ],
        ]);
    }

    public function show(Guidebook $guidebook): Response
    {
        $guidebook->load(['category:id,name,slug,icon,color', 'creator:id,name,avatar_path']);

        // Hitungan tampilan sengaja tidak menyentuh updated_at agar urutan
        // "terakhir diperbarui" di index tetap mencerminkan perubahan konten.
        $guidebook->newQuery()->whereKey($guidebook->id)->increment('view_count');

        return Inertia::render('guidebooks/show', [
            'guidebook' => $this->detailPayload($guidebook),
            // Dibutuhkan dialog edit agar kategori bisa dipindah dari halaman ini.
            'categories' => GuidebookCategory::orderBy('order')->orderBy('name')->get(),
            'can' => [
                'manage' => $this->canManage(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $this->validatePayload($request);

        $guidebook = new Guidebook([
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'slug' => Guidebook::uniqueSlug($validated['title']),
            'description' => $validated['description'] ?? null,
            'content_type' => $validated['content_type'],
            'is_pinned' => $request->boolean('is_pinned'),
        ]);
        $guidebook->created_by = Auth::id();
        $guidebook->fill($this->contentFields($validated));
        $guidebook->save();

        $this->storeFiles($request, $guidebook);

        return redirect()->route('guidebooks.show', $guidebook)
            ->with('success', 'Guidebook berhasil dibuat.');
    }

    public function update(Request $request, Guidebook $guidebook): RedirectResponse
    {
        $this->authorizeManage();

        $validated = $this->validatePayload($request);
        $previousType = $guidebook->content_type;

        $guidebook->fill([
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'slug' => Guidebook::uniqueSlug($validated['title'], $guidebook->id),
            'description' => $validated['description'] ?? null,
            'content_type' => $validated['content_type'],
            'is_pinned' => $request->boolean('is_pinned'),
        ]);
        $guidebook->fill($this->contentFields($validated));

        // Berpindah dari tipe pdf ke tipe lain: berkas lama tidak lagi terpakai.
        if ($previousType === 'pdf' && $validated['content_type'] !== 'pdf' && $guidebook->getOriginal('pdf_path')) {
            GuidebookAttachmentStorage::delete($guidebook->getOriginal('pdf_path'));
            $guidebook->pdf_path = null;
        }

        $guidebook->save();

        $this->storeFiles($request, $guidebook);

        return redirect()->route('guidebooks.show', $guidebook)
            ->with('success', 'Guidebook berhasil diperbarui.');
    }

    public function destroy(Guidebook $guidebook): RedirectResponse
    {
        $this->authorizeManage();

        GuidebookAttachmentStorage::delete($guidebook->pdf_path);
        foreach ($guidebook->attachments ?? [] as $attachment) {
            GuidebookAttachmentStorage::delete($attachment['path'] ?? null, $attachment['disk'] ?? 'public');
        }

        $guidebook->delete();

        return redirect()->route('guidebooks.index')
            ->with('success', 'Guidebook berhasil dihapus.');
    }

    /**
     * Toggle the "pinned" flag straight from the index card.
     */
    public function togglePin(Guidebook $guidebook): RedirectResponse
    {
        $this->authorizeManage();

        $guidebook->update(['is_pinned' => ! $guidebook->is_pinned]);

        return redirect()->back()->with(
            'success',
            $guidebook->is_pinned ? 'Guidebook disematkan.' : 'Sematan guidebook dilepas.'
        );
    }

    /**
     * Stream the main PDF inline. Served through the app (instead of a raw
     * public-disk URL) so the guidebooks.view permission still applies.
     */
    public function pdf(Guidebook $guidebook): StreamedResponse
    {
        abort_unless($guidebook->pdf_path && Storage::disk('public')->exists($guidebook->pdf_path), 404);

        return Storage::disk('public')->response($guidebook->pdf_path, $guidebook->slug . '.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addcslashes($guidebook->slug . '.pdf', '"\\') . '"',
        ]);
    }

    /**
     * Validation shared by store() and update(). Each content type only
     * requires the fields its own viewer needs.
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'category_id' => 'required|integer|exists:guidebook_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'content_type' => ['required', Rule::in(Guidebook::CONTENT_TYPES)],
            'embed_url' => 'required_if:content_type,embed,video|nullable|url|max:2000',
            'content' => 'required_if:content_type,native|nullable|string|max:200000',
            'checklist_items' => 'required_if:content_type,checklist|nullable|array|max:100',
            'checklist_items.*.text' => 'required|string|max:500',
            'checklist_items.*.note' => 'nullable|string|max:1000',
            'pdf' => [
                Rule::requiredIf(fn () => $request->input('content_type') === 'pdf' && ! $request->route('guidebook')),
                'nullable',
                ...explode('|', GuidebookAttachmentStorage::pdfValidationRule()),
            ],
            'attachments' => 'nullable|array|max:' . (int) config('guidebook.attachments.max_files', 10),
            'attachments.*' => GuidebookAttachmentStorage::attachmentValidationRule(),
            'is_pinned' => 'nullable|boolean',
        ]);
    }

    /**
     * Null out the fields that do not belong to the selected content type so a
     * type switch never leaves stale data behind.
     */
    private function contentFields(array $validated): array
    {
        $type = $validated['content_type'];

        return [
            'embed_url' => in_array($type, ['embed', 'video'], true) ? ($validated['embed_url'] ?? null) : null,
            'content' => $type === 'native' ? ($validated['content'] ?? null) : null,
            'checklist_items' => $type === 'checklist' ? array_values($validated['checklist_items'] ?? []) : null,
        ];
    }

    private function storeFiles(Request $request, Guidebook $guidebook): void
    {
        if ($request->hasFile('pdf') && $guidebook->content_type === 'pdf') {
            GuidebookAttachmentStorage::delete($guidebook->pdf_path);
            $guidebook->pdf_path = GuidebookAttachmentStorage::storePdf($guidebook, $request->file('pdf'));
            $guidebook->save();
        }

        $files = $request->file('attachments', []);
        if (! empty($files)) {
            $guidebook->attachments = array_merge(
                $guidebook->attachments ?? [],
                GuidebookAttachmentStorage::storeAttachments($guidebook, $files)
            );
            $guidebook->save();
        }
    }

    private function summaryPayload(Guidebook $guidebook): array
    {
        return [
            'id' => $guidebook->id,
            'title' => $guidebook->title,
            'slug' => $guidebook->slug,
            'description' => $guidebook->description,
            'content_type' => $guidebook->content_type,
            'is_pinned' => $guidebook->is_pinned,
            'view_count' => $guidebook->view_count,
            'category' => $guidebook->category,
            'creator' => $guidebook->creator,
            'checklist_count' => count($guidebook->checklist_items ?? []),
            'attachments_count' => count($guidebook->attachments ?? []),
            'updated_at' => $guidebook->updated_at,
        ];
    }

    private function detailPayload(Guidebook $guidebook): array
    {
        return array_merge($this->summaryPayload($guidebook), [
            'embed_url' => $guidebook->embed_url,
            'content' => $guidebook->content,
            'checklist_items' => $guidebook->checklist_items ?? [],
            'attachments' => $guidebook->attachments ?? [],
            'pdf_url' => $guidebook->pdf_path
                ? route('guidebooks.pdf', $guidebook)
                : null,
            'created_at' => $guidebook->created_at,
        ]);
    }

    private function canManage(): bool
    {
        $user = Auth::user();

        return $user && ($user->isAdmin() || $user->hasPermissionTo('guidebooks.manage'));
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403);
    }
}
