<?php

namespace App\Domain\Orders\Models;

use Database\Factories\OrderRefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderRefund extends Model
{
    /** @use HasFactory<OrderRefundFactory> */
    use HasFactory;

    protected static function newFactory(): OrderRefundFactory
    {
        return OrderRefundFactory::new();
    }

    protected $table = 'order_refunds';

    protected $guarded = ['id'];

    protected $casts = ['items' => 'array', 'amount' => 'integer'];
}
