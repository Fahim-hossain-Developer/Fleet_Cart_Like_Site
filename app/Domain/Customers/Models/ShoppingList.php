<?php

namespace App\Domain\Customers\Models;

use Database\Factories\ShoppingListFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingList extends Model
{
    /** @use HasFactory<ShoppingListFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['owner_key'];

    protected static function newFactory(): ShoppingListFactory
    {
        return ShoppingListFactory::new();
    }
}
