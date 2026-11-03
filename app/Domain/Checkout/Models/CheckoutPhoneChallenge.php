<?php

namespace App\Domain\Checkout\Models;

use Database\Factories\CheckoutPhoneChallengeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckoutPhoneChallenge extends Model
{
    /** @use HasFactory<CheckoutPhoneChallengeFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['expires_at' => 'datetime', 'verified_at' => 'datetime', 'used_at' => 'datetime', 'attempts' => 'integer'];

    protected $attributes = ['attempts' => 0];

    protected $hidden = ['code_hash', 'phone_hash'];

    protected static function newFactory(): CheckoutPhoneChallengeFactory
    {
        return CheckoutPhoneChallengeFactory::new();
    }
}
