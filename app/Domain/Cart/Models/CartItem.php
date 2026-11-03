<?php

namespace App\Domain\Cart\Models;

use App\Domain\Catalog\Models\Product;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    protected static function newFactory(): CartItemFactory
    {
        return CartItemFactory::new();
    }

    protected $table = 'cart_items';

    protected $fillable = ['cart_id', 'product_id', 'variant_id', 'line_key', 'qty', 'options', 'saved', 'product_package_id', 'package_units'];

    protected $casts = ['options' => 'array', 'saved' => 'boolean', 'qty' => 'integer', 'package_units' => 'integer'];

    /**
     * @return BelongsTo<Product,$this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
