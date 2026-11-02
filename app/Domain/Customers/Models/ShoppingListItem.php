<?php

namespace App\Domain\Customers\Models;

use Database\Factories\ShoppingListItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingListItem extends Model
{
    /** @use HasFactory<ShoppingListItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['owner_key'];

    protected static function newFactory(): ShoppingListItemFactory
    {
        return ShoppingListItemFactory::new();
    }
}
