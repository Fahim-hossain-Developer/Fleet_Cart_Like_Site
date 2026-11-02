<?php

namespace App\Domain\Orders\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    protected $table = 'orders';

    protected $attributes = ['status' => 'pending', 'payment_status' => 'unpaid', 'fulfillment_status' => 'unfulfilled', 'paid_amount' => 0];

    protected $guarded = ['id'];

    protected $casts = ['shipping_address' => 'array', 'placed_at' => 'datetime', 'is_gift' => 'boolean', 'hide_prices' => 'boolean', 'total' => 'integer', 'display_total' => 'integer'];

    /**
     * @return HasMany<OrderItem,$this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
