<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\Post;

class PostResource extends ContentResource
{
    protected static ?string $model = Post::class;

    protected static ?string $slug = 'posts';

    public static function getPages(): array
    {
        return ['index' => PostResource\Pages\ManageRecords::route('/')];
    }
}
