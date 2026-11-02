<?php

namespace App\Domain\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

class CourierCollection extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'integer'];
}
