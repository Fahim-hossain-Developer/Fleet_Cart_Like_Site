<?php

namespace App\Domain\Customers\Models;

use Database\Factories\NoticeDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoticeDelivery extends Model
{
    /** @use HasFactory<NoticeDeliveryFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['sent_at' => 'datetime', 'attempts' => 'integer'];

    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    protected static function newFactory(): NoticeDeliveryFactory
    {
        return NoticeDeliveryFactory::new();
    }
}
