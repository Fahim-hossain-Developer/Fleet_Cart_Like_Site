<?php

namespace App\Domain\Orders\Models;

use Database\Factories\OrderEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    /** @use HasFactory<OrderEventFactory> */
    use HasFactory;

    protected static function newFactory(): OrderEventFactory
    {
        return OrderEventFactory::new();
    }

    protected $table = 'order_events';

    protected $guarded = ['id'];

    protected $casts = ['is_public' => 'boolean', 'notify' => 'boolean', 'sent_at' => 'datetime'];
}
