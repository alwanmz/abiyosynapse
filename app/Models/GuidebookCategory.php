<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuidebookCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function guidebooks(): HasMany
    {
        return $this->hasMany(Guidebook::class, 'category_id');
    }
}
