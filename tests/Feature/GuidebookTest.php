<?php

namespace Tests\Feature;

use App\Models\Guidebook;
use App\Models\GuidebookCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuidebookTest extends TestCase
{
    use RefreshDatabase;

    private GuidebookCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'programmer'], ['display_name' => 'Programmer']);

        $this->category = GuidebookCategory::create([
            'name' => 'Panduan Sistem',
            'slug' => 'panduan-sistem',
            'order' => 0,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);
    }

    public function test_admin_can_create_embed_guidebook(): void
    {
        $this->actingAs($this->admin())
            ->post(route('guidebooks.store'), [
                'category_id' => $this->category->id,
                'title' => 'Portal Dokumentasi Internal',
                'content_type' => 'embed',
                'embed_url' => 'https://sites.google.com/view/teamboard-sop',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('guidebooks', [
            'title' => 'Portal Dokumentasi Internal',
            'content_type' => 'embed',
            'slug' => 'portal-dokumentasi-internal',
            'embed_url' => 'https://sites.google.com/view/teamboard-sop',
        ]);
    }

    public function test_admin_can_create_video_guidebook(): void
    {
        $this->actingAs($this->admin())
            ->post(route('guidebooks.store'), [
                'category_id' => $this->category->id,
                'title' => 'Video Walkthrough Modul Tiket',
                'content_type' => 'video',
                'embed_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('guidebooks', [
            'title' => 'Video Walkthrough Modul Tiket',
            'content_type' => 'video',
        ]);
    }

    public function test_admin_can_create_pdf_guidebook_and_file_is_stored(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('guidebooks.store'), [
                'category_id' => $this->category->id,
                'title' => 'SOP Rilis Resmi',
                'content_type' => 'pdf',
                'pdf' => UploadedFile::fake()->create('sop.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $guidebook = Guidebook::where('title', 'SOP Rilis Resmi')->firstOrFail();

        $this->assertNotNull($guidebook->pdf_path);
        Storage::disk('public')->assertExists($guidebook->pdf_path);
    }

    public function test_admin_can_create_native_guidebook(): void
    {
        $this->actingAs($this->admin())
            ->post(route('guidebooks.store'), [
                'category_id' => $this->category->id,
                'title' => 'Standar Penamaan Branch',
                'content_type' => 'native',
                'content' => '<h2>Konvensi</h2><p>Gunakan prefix feature/ atau fix/.</p>',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('guidebooks', [
            'title' => 'Standar Penamaan Branch',
            'content_type' => 'native',
        ]);
    }

    public function test_admin_can_create_checklist_guidebook(): void
    {
        $this->actingAs($this->admin())
            ->post(route('guidebooks.store'), [
                'category_id' => $this->category->id,
                'title' => 'Checklist Rilis Maintenance',
                'content_type' => 'checklist',
                'checklist_items' => [
                    ['text' => 'Backup database produksi', 'note' => 'Simpan di storage terenkripsi'],
                    ['text' => 'Jalankan migrasi'],
                ],
            ])
            ->assertRedirect();

        $guidebook = Guidebook::where('title', 'Checklist Rilis Maintenance')->firstOrFail();

        $this->assertCount(2, $guidebook->checklist_items);
        $this->assertSame('Backup database produksi', $guidebook->checklist_items[0]['text']);
    }

    public function test_embed_guidebook_requires_url(): void
    {
        $this->actingAs($this->admin())
            ->post(route('guidebooks.store'), [
                'category_id' => $this->category->id,
                'title' => 'Tanpa URL',
                'content_type' => 'embed',
            ])
            ->assertSessionHasErrors('embed_url');
    }

    public function test_checklist_guidebook_requires_items(): void
    {
        $this->actingAs($this->admin())
            ->post(route('guidebooks.store'), [
                'category_id' => $this->category->id,
                'title' => 'Checklist Kosong',
                'content_type' => 'checklist',
            ])
            ->assertSessionHasErrors('checklist_items');
    }

    public function test_show_page_renders_and_increments_view_count(): void
    {
        $admin = $this->admin();
        $guidebook = Guidebook::create([
            'category_id' => $this->category->id,
            'created_by' => $admin->id,
            'title' => 'Panduan Onboarding',
            'slug' => 'panduan-onboarding',
            'content_type' => 'native',
            'content' => '<p>Selamat datang.</p>',
        ]);

        $this->actingAs($admin)
            ->get(route('guidebooks.show', $guidebook))
            ->assertOk();

        $this->assertSame(1, $guidebook->fresh()->view_count);
    }

    public function test_switching_content_type_clears_previous_type_fields(): void
    {
        $admin = $this->admin();
        $guidebook = Guidebook::create([
            'category_id' => $this->category->id,
            'created_by' => $admin->id,
            'title' => 'Berubah Tipe',
            'slug' => 'berubah-tipe',
            'content_type' => 'embed',
            'embed_url' => 'https://sites.google.com/view/lama',
        ]);

        $this->actingAs($admin)
            ->put(route('guidebooks.update', $guidebook), [
                'category_id' => $this->category->id,
                'title' => 'Berubah Tipe',
                'content_type' => 'native',
                'content' => '<p>Sekarang artikel.</p>',
            ])
            ->assertRedirect();

        $guidebook->refresh();

        $this->assertSame('native', $guidebook->content_type);
        $this->assertNull($guidebook->embed_url);
    }

    public function test_slug_stays_unique_across_guidebooks_with_same_title(): void
    {
        $admin = $this->admin();

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($admin)->post(route('guidebooks.store'), [
                'category_id' => $this->category->id,
                'title' => 'Judul Kembar',
                'content_type' => 'native',
                'content' => '<p>Isi.</p>',
            ]);
        }

        $this->assertDatabaseHas('guidebooks', ['slug' => 'judul-kembar']);
        $this->assertDatabaseHas('guidebooks', ['slug' => 'judul-kembar-2']);
    }

    public function test_user_without_manage_permission_cannot_create_guidebook(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'programmer')->first()->id,
        ]);

        $this->actingAs($user)
            ->post(route('guidebooks.store'), [
                'category_id' => $this->category->id,
                'title' => 'Percobaan',
                'content_type' => 'native',
                'content' => '<p>Isi.</p>',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('guidebooks', ['title' => 'Percobaan']);
    }

    public function test_toggle_pin_flips_the_flag(): void
    {
        $admin = $this->admin();
        $guidebook = Guidebook::create([
            'category_id' => $this->category->id,
            'created_by' => $admin->id,
            'title' => 'Untuk Disematkan',
            'slug' => 'untuk-disematkan',
            'content_type' => 'native',
            'content' => '<p>Isi.</p>',
        ]);

        $this->actingAs($admin)->post(route('guidebooks.pin', $guidebook))->assertRedirect();
        $this->assertTrue($guidebook->fresh()->is_pinned);

        $this->actingAs($admin)->post(route('guidebooks.pin', $guidebook))->assertRedirect();
        $this->assertFalse($guidebook->fresh()->is_pinned);
    }
}
