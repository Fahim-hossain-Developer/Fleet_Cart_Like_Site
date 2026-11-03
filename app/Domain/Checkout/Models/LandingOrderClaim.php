<?php

namespace App\Domain\Checkout\Models;

use Database\Factories\LandingOrderClaimFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingOrderClaim extends Model
{
    /** @use HasFactory<LandingOrderClaimFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['expires_at' => 'datetime'];

    protected $attributes = [];

    protected $hidden = ['identity_hash'];

    protected static function newFactory(): LandingOrderClaimFactory
    {
        return LandingOrderClaimFactory::new();
    }
}
