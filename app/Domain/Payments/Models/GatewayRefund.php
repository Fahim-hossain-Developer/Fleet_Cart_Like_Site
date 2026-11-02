<?php

namespace App\Domain\Payments\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $amount
 */
class GatewayRefund extends Model
{
    protected $attributes = ['mode' => 'sandbox', 'status' => 'created'];

    protected $table = 'gateway_refunds';

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'integer', 'verified_amount' => 'integer', 'restock_items' => 'array', 'verified_at' => 'datetime', 'lease_until' => 'datetime'];

    protected $hidden = [];
}
