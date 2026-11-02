<?php

namespace App\Domain\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $mode
 * @property int $cod_expected
 * @property int|null $reported_collected
 * @property int $remitted_amount
 * @property int|null $delivery_fee
 */
class CourierBooking extends Model
{
    protected $attributes = ['mode' => 'sandbox', 'status' => 'created', 'remitted_amount' => 0];

    protected $table = 'courier_bookings';

    protected $guarded = ['id'];

    protected $casts = ['items' => 'array', 'cod_expected' => 'integer', 'reported_collected' => 'integer', 'remitted_amount' => 'integer', 'delivery_fee' => 'integer', 'lease_until' => 'datetime', 'last_checked_at' => 'datetime'];

    protected $hidden = [];
}
