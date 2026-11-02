<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\CartRecoveryRestorationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartRecoveryRestoration extends Model
{
    /** @use HasFactory<CartRecoveryRestorationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['generation' => 'integer'];

    protected $attributes = [];

    protected $hidden = [];

    protected static function newFactory(): CartRecoveryRestorationFactory
    {
        return CartRecoveryRestorationFactory::new();
    }
}
