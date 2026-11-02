<?php

namespace App\Domain\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $amount
 */
class CourierRemittance extends Model
{
    protected $table = 'courier_remittances';

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'integer'];

    protected $hidden = [];
}
