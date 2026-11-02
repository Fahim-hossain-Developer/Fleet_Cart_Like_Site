<?php

namespace App\Domain\Customers\Models;

use Database\Factories\ProductWatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductWatch extends Model
{
    /** @use HasFactory<ProductWatchFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'last_available' => 'boolean', 'last_price' => 'integer', 'sequence' => 'integer', 'notified_at' => 'datetime'];

    protected $attributes = ['is_active' => true, 'last_available' => false, 'last_price' => 0, 'sequence' => 0];

    protected static function newFactory(): ProductWatchFactory
    {
        return ProductWatchFactory::new();
    }
}
