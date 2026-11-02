<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\BadgeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends CatalogModel
{
    /** @use HasFactory<BadgeFactory> */
    use HasFactory;

    protected static function newFactory(): BadgeFactory
    {
        return BadgeFactory::new();
    }

    /** @var list<string> */
    public $translatable = ['label'];

    protected $casts = ['rule' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }
}
