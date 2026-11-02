<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\CartRecoveryContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartRecoveryContact extends Model
{
    /** @use HasFactory<CartRecoveryContactFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['captured_name' => 'encrypted', 'email' => 'encrypted', 'phone' => 'encrypted', 'stop_token' => 'encrypted', 'channels' => 'array', 'generation' => 'integer', 'sms_attempts' => 'integer', 'consented_at' => 'datetime', 'last_activity_at' => 'datetime', 'email_verified_at' => 'datetime', 'phone_verified_at' => 'datetime', 'confirmation_expires_at' => 'datetime', 'sms_expires_at' => 'datetime', 'stopped_at' => 'datetime', 'converted_at' => 'datetime'];

    protected $attributes = ['generation' => 0, 'sms_attempts' => 0, 'locale' => 'en'];

    protected $hidden = ['captured_name', 'email', 'phone', 'email_hash', 'phone_hash', 'stop_token', 'stop_hash', 'confirmation_hash', 'sms_code_hash'];

    protected static function newFactory(): CartRecoveryContactFactory
    {
        return CartRecoveryContactFactory::new();
    }
}
