<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\ProductTabFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductTab extends CatalogModel
{
    /** @use HasFactory<ProductTabFactory> */
    use HasFactory;

    protected static function newFactory(): ProductTabFactory
    {
        return ProductTabFactory::new();
    }

    /** @var list<string> */
    public $translatable = ['title', 'body'];

    protected $casts = ['category_ids' => 'array', 'is_active' => 'boolean'];
}
