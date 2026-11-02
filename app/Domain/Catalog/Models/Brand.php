<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property bool $is_active
 * @property string $name */
class Brand extends CatalogModel
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    protected static function newFactory(): BrandFactory
    {
        return BrandFactory::new();
    }

    /** @var list<string> */
    public $translatable = ['name', 'description'];

    protected $casts = ['seo' => 'array', 'is_active' => 'boolean'];

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
