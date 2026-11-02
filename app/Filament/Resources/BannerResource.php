<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\Banner;

class BannerResource extends ContentResource
{
    protected static ?string $model = Banner::class;

    protected static ?string $slug = 'banners';

    public static function getPages(): array
    {
        return ['index' => BannerResource\Pages\ManageRecords::route('/')];
    }
}
