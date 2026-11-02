<?php

namespace App\Domain\Content\Models;

use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends ContentModel
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    protected $table = 'menus';

    protected static function newFactory(): MenuFactory
    {
        return MenuFactory::new();
    }

    /** @return HasMany<MenuItem,$this> */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort')->orderBy('id');
    }
}
