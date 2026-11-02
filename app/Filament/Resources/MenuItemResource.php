<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\MenuItem;

class MenuItemResource extends ContentResource
{
    protected static ?string $model = MenuItem::class;

    protected static ?string $slug = 'menu-items';

    public static function getPages(): array
    {
        return ['index' => MenuItemResource\Pages\ManageRecords::route('/')];
    }
}
