<?php

namespace App\Domain\Orders\Models;

use Database\Factories\OrderReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderReturn extends Model
{
    /** @use HasFactory<OrderReturnFactory> */
    use HasFactory;

    protected static function newFactory(): OrderReturnFactory
    {
        return OrderReturnFactory::new();
    }

    protected $table = 'order_returns';

    protected $guarded = ['id'];

    protected $casts = ['items' => 'array'];
}
