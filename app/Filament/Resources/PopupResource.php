<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\Popup;

class PopupResource extends ContentResource
{
    protected static ?string $model = Popup::class;

    protected static ?string $slug = 'popups';

    public static function getPages(): array
    {
        return ['index' => PopupResource\Pages\ManageRecords::route('/')];
    }
}
