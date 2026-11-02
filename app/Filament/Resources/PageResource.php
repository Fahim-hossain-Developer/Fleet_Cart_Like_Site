<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\Page;

class PageResource extends ContentResource
{
    protected static ?string $model = Page::class;

    protected static ?string $slug = 'pages';

    public static function getPages(): array
    {
        return ['index' => PageResource\Pages\ManageRecords::route('/')];
    }
}
