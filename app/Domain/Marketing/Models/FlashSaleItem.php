<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Marketing\Actions\ValidatePromotion;
use Database\Factories\FlashSaleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleItem extends Model
{
    /** @use HasFactory<FlashSaleItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['price' => 'integer', 'quota' => 'integer', 'sold' => 'integer', 'is_active' => 'boolean', 'variant_key' => 'integer'];

    protected $attributes = ['sold' => 0, 'is_active' => true, 'variant_key' => 0];

    protected static function newFactory(): FlashSaleItemFactory
    {
        return FlashSaleItemFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(fn (self $m) => app(ValidatePromotion::class)->execute($m));
    }

    /** @return BelongsTo<FlashSale,$this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class, 'flash_sale_id');
    }
}
