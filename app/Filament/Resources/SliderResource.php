<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\Slider;

class SliderResource extends ContentResource
{
    protected static ?string $model = Slider::class;

    protected static ?string $slug = 'sliders';

    public static function getPages(): array
    {
        return ['index' => SliderResource\Pages\ManageRecords::route('/')];
    }
}
