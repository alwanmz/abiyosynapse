<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    /** @use HasFactory<\Database\Factories\ProductCategoryFactory> */
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
