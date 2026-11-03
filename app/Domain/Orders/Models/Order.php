<?php

namespace App\Domain\Orders\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;

/**
 * @property string|null $payment_environment
 * @property int $advance_required
 * @property int $subtotal
 * @property int $discount
 * @property int $shipping
 * @property int $tax
 * @property int $tax_included
 * @property int $gift_fee
 * @property int $total
 * @property int $paid_amount
 * @property int $refunded_amount
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, \Spatie\Activitylog\Traits\LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'payment_status', 'fulfillment_status', 'paid_amount', 'refunded_amount'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    protected $table = 'orders';

    protected $attributes = ['status' => 'pending', 'payment_status' => 'unpaid', 'fulfillment_status' => 'unfulfilled', 'paid_amount' => 0, 'refunded_amount' => 0];

    protected $guarded = ['id'];

    protected $hidden = ['checkout_meta'];

    protected $casts = ['landing_snapshot' => 'array', 'checkout_meta' => 'encrypted:array', 'automatic_discount' => 'integer', 'coupon_discount' => 'integer', 'cart_rule_snapshot' => 'array', 'shipping_address' => 'array', 'placed_at' => 'datetime', 'is_gift' => 'boolean', 'hide_prices' => 'boolean', 'total' => 'integer', 'display_total' => 'integer', 'risk_flags' => 'array', 'paid_amount' => 'integer', 'refunded_amount' => 'integer'];

    /**
     * @return HasMany<OrderItem,$this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderEvent,$this> */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    /** @return HasMany<OrderPayment,$this> */
    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    /** @return HasMany<Shipment,$this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @return HasMany<OrderReturn,$this> */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    /** @return HasMany<OrderRefund,$this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class);
    }
}
