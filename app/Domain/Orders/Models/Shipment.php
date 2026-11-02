<?php

namespace App\Domain\Orders\Models;

use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    protected static function newFactory(): ShipmentFactory
    {
        return ShipmentFactory::new();
    }

    protected $table = 'shipments';

    protected $guarded = ['id'];

    protected $casts = ['items' => 'array'];
}
