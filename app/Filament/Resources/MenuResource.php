<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\Menu;

class MenuResource extends ContentResource
{
    protected static ?string $model = Menu::class;

    protected static ?string $slug = 'menus';

    public static function getPages(): array
    {
        return ['index' => MenuResource\Pages\ManageRecords::route('/')];
    }
}
