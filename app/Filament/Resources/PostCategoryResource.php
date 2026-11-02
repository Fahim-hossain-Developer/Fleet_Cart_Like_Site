<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\PostCategory;

class PostCategoryResource extends ContentResource
{
    protected static ?string $model = PostCategory::class;

    protected static ?string $slug = 'post-categories';

    public static function getPages(): array
    {
        return ['index' => PostCategoryResource\Pages\ManageRecords::route('/')];
    }
}
