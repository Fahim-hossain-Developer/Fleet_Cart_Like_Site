<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\Slide;

class SlideResource extends ContentResource
{
    protected static ?string $model = Slide::class;

    protected static ?string $slug = 'slides';

    public static function getPages(): array
    {
        return ['index' => SlideResource\Pages\ManageRecords::route('/')];
    }
}
