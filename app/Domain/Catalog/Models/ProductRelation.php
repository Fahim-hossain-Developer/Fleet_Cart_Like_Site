<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\ProductRelationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRelation extends CatalogModel
{
    /** @use HasFactory<ProductRelationFactory> */
    use HasFactory;

    protected static function newFactory(): ProductRelationFactory
    {
        return ProductRelationFactory::new();
    }

    /** @var list<string> */
    public $translatable = [];

    protected $casts = [];

    /** @return BelongsTo<Product, $this> */
    public function related(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_id');
    }
}
