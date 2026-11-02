<?php

namespace App\Domain\Cart\Models;

use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    protected static function newFactory(): CartFactory
    {
        return CartFactory::new();
    }

    protected $table = 'carts';

    protected $fillable = ['token', 'user_id', 'coupon_id'];

    protected $casts = [];

    /**
     * @return HasMany<CartItem,$this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
