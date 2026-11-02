<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @property string $name */
class Tag extends CatalogModel
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
    }

    /** @var list<string> */
    public $translatable = ['name', 'description'];

    protected $casts = ['is_active' => 'boolean'];

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'tag_product');
    }
}
