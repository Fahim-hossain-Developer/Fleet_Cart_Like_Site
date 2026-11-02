<?php

namespace App\Domain\Customers\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int|null $sms_delivery_id */
class PhoneChallenge extends Model
{
    protected $attributes = ['attempts' => 0];

    protected $table = 'phone_challenges';

    protected $guarded = ['id'];

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];

    protected $hidden = ['code_hash'];
}
