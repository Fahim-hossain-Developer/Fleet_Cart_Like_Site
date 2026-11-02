<?php

namespace App\Domain\Content\Models;

use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MenuItem extends ContentModel
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected $table = 'menu_items';

    protected static function newFactory(): MenuItemFactory
    {
        return MenuItemFactory::new();
    }
}
