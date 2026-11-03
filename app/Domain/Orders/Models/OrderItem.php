<?php

namespace App\Domain\Orders\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool $tax_inclusive
 * @property int $subtotal
 * @property int $discount
 * @property int $tax
 * @property int $total
 * @property int $tax_bps
 * @property int $stock_deducted
 * @property int $restocked_qty
 * @property int $qty
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }

    protected $table = 'order_items';

    protected $guarded = ['id'];

    protected $casts = ['package_snapshot' => 'array', 'tax_inclusive' => 'boolean', 'qty' => 'integer', 'stock_deducted' => 'integer', 'restocked_qty' => 'integer', 'options' => 'array', 'unit_price' => 'integer', 'total' => 'integer'];

    /** @return BelongsTo<Order,$this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
