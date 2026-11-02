<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Catalog\Models\CatalogModel;
use App\Domain\Marketing\Actions\ValidatePromotion;
use Database\Factories\CartRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartRule extends CatalogModel
{
    /** @use HasFactory<CartRuleFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'stackable' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'minimum' => 'integer', 'minimum_qty' => 'integer', 'priority' => 'integer', 'value' => 'integer', 'max_discount' => 'integer'];

    protected $attributes = ['is_active' => false, 'priority' => 100, 'minimum' => 0, 'minimum_qty' => 1, 'value' => 0, 'stackable' => false];

    protected static function newFactory(): CartRuleFactory
    {
        return CartRuleFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(fn (self $m) => app(ValidatePromotion::class)->execute($m));
    }
}
