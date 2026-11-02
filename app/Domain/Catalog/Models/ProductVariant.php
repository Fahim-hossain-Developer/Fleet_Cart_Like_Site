<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Orders\Models\OrderItem;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/** @property array<string,string> $attributes */
class ProductVariant extends CatalogModel
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $variant) {
            if (OrderItem::where('variant_id', $variant->id)->exists()) {
                throw ValidationException::withMessages(['variant' => __('orders.variant_in_use')]);
            }
        });
    }

    /** @var list<string> */
    public $translatable = [];

    protected $attributes = ['is_active' => true, 'qty' => 0];

    protected $casts = ['attributes' => 'array', 'price' => 'integer', 'special_price' => 'integer', 'qty' => 'integer', 'is_active' => 'boolean'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
