<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\CartRecoveryMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int|null $sms_delivery_id */
class CartRecoveryMessage extends Model
{
    /** @use HasFactory<CartRecoveryMessageFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['payload' => 'encrypted:array', 'snapshot' => 'array', 'generation' => 'integer', 'stage' => 'integer', 'attempts' => 'integer', 'expires_at' => 'datetime', 'sent_at' => 'datetime', 'used_at' => 'datetime'];

    protected $attributes = ['status' => 'pending', 'attempts' => 0, 'stage' => 0];

    protected $hidden = ['payload', 'snapshot', 'link_hash'];

    protected static function newFactory(): CartRecoveryMessageFactory
    {
        return CartRecoveryMessageFactory::new();
    }
}
