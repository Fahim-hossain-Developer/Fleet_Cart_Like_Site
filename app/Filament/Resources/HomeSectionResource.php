<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\HomeSection;

class HomeSectionResource extends ContentResource
{
    protected static ?string $model = HomeSection::class;

    protected static ?string $slug = 'home-sections';

    public static function getPages(): array
    {
        return ['index' => HomeSectionResource\Pages\ManageRecords::route('/')];
    }
}
