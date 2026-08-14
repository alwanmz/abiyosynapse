<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Guidebook extends Model
{
    /**
     * The five supported content types. Each one drives a different viewer on
     * the frontend and a different set of required fields on the form.
     */
    public const CONTENT_TYPES = ['embed', 'video', 'pdf', 'native', 'checklist'];

    protected $fillable = [
        'category_id',
        'created_by',
        'title',
        'slug',
        'description',
        'content_type',
        'embed_url',
        'pdf_path',
        'content',
        'checklist_items',
        'is_pinned',
        'attachments',
    ];

    protected $casts = [
        'checklist_items' => 'array',
        'attachments' => 'array',
        'is_pinned' => 'boolean',
        'view_count' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(GuidebookCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Build a slug from the title that does not collide with an existing one.
     * `$ignoreId` lets an update keep its own slug instead of bumping it.
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'guidebook';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
