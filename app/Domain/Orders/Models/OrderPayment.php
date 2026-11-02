<?php

namespace App\Domain\Orders\Models;

use Database\Factories\OrderPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    /** @use HasFactory<OrderPaymentFactory> */
    use HasFactory;

    protected static function newFactory(): OrderPaymentFactory
    {
        return OrderPaymentFactory::new();
    }

    protected $table = 'order_payments';

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'integer'];
}
