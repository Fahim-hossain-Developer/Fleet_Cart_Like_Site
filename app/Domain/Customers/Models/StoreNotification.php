<?php

namespace App\Domain\Customers\Models;

use Database\Factories\StoreNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification;

class StoreNotification extends DatabaseNotification
{
    /** @use HasFactory<StoreNotificationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string,string> */
    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];

    protected $attributes = [];

    protected static function newFactory(): StoreNotificationFactory
    {
        return StoreNotificationFactory::new();
    }

    protected $table = 'notifications';
}
