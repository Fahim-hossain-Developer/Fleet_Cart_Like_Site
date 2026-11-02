<?php

namespace App\Domain\Orders\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    protected $casts = ['options' => 'array', 'unit_price' => 'integer', 'total' => 'integer'];
}
