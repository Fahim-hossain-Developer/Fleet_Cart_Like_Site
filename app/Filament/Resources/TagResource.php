<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Tag;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class TagResource extends CatalogResource
{
    protected static ?string $model = Tag::class;

    protected static ?string $slug = 'tags';

    public static function form(Form $form): Form
    {
        return $form->schema([...static::translated('name', required: true), TextInput::make('slug')->required()->unique(ignoreRecord: true), Toggle::make('is_active')->default(true)])->columns(2);
    }

    public static function getPages(): array
    {
        return ['index' => TagResource\Pages\ManageRecords::route('/')];
    }
}
