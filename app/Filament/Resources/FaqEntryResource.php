<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\FaqEntry;

class FaqEntryResource extends ContentResource
{
    protected static ?string $model = FaqEntry::class;

    protected static ?string $slug = 'faq-entries';

    public static function getPages(): array
    {
        return ['index' => FaqEntryResource\Pages\ManageRecords::route('/')];
    }
}
