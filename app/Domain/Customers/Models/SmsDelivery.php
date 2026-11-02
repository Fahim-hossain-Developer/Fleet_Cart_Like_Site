<?php

namespace App\Domain\Customers\Models;

use Illuminate\Database\Eloquent\Model;

/** @property string $mode */
class SmsDelivery extends Model
{
    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    protected $table = 'sms_deliveries';

    protected $guarded = ['id'];

    protected $casts = ['receiver' => 'encrypted', 'payload' => 'encrypted', 'expires_at' => 'datetime', 'sent_at' => 'datetime'];

    protected $hidden = ['receiver', 'payload'];
}
