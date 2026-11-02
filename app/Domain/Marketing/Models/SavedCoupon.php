<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\SavedCouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedCoupon extends Model
{
    /** @use HasFactory<SavedCouponFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [];

    protected $attributes = [];

    protected static function newFactory(): SavedCouponFactory
    {
        return SavedCouponFactory::new();
    }
}
