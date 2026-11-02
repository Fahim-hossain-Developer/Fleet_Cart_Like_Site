<?php

namespace App\Domain\Customers\Models;

use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }

    protected $guarded = ['id'];

    protected $casts = ['is_default' => 'boolean'];
}
