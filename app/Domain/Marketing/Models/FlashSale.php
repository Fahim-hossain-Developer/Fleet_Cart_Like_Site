<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Catalog\Models\CatalogModel;
use App\Domain\Marketing\Actions\ValidatePromotion;
use Database\Factories\FlashSaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSale extends CatalogModel
{
    /** @use HasFactory<FlashSaleFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    protected $attributes = ['is_active' => false];

    protected static function newFactory(): FlashSaleFactory
    {
        return FlashSaleFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(fn (self $m) => app(ValidatePromotion::class)->execute($m));
    }

    /** @return HasMany<FlashSaleItem,$this> */
    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class);
    }
}
