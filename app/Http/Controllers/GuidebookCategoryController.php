<?php

namespace App\Http\Controllers;

use App\Models\GuidebookCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GuidebookCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        GuidebookCategory::create(array_merge($validated, [
            'slug' => $this->uniqueSlug($validated['name']),
        ]));

        return redirect()->back()->with('success', 'Kategori guidebook berhasil dibuat.');
    }

    public function update(Request $request, GuidebookCategory $guidebookCategory): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $guidebookCategory->update(array_merge($validated, [
            'slug' => $this->uniqueSlug($validated['name'], $guidebookCategory->id),
        ]));

        return redirect()->back()->with('success', 'Kategori guidebook berhasil diperbarui.');
    }

    public function destroy(GuidebookCategory $guidebookCategory): RedirectResponse
    {
        // Guidebook ikut terhapus lewat cascade, jadi minta konfirmasi eksplisit
        // dengan menolak kategori yang masih berisi.
        if ($guidebookCategory->guidebooks()->exists()) {
            return redirect()->back()->withErrors([
                'category' => 'Kategori masih memiliki guidebook. Pindahkan atau hapus isinya terlebih dahulu.',
            ]);
        }

        $guidebookCategory->delete();

        return redirect()->back()->with('success', 'Kategori guidebook berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:30',
            'order' => 'nullable|integer|min:0|max:999',
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $suffix = 2;

        while (GuidebookCategory::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
