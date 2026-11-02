<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\AttributeSet;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class AttributeSetResource extends CatalogResource
{
    protected static ?string $model = AttributeSet::class;

    protected static ?string $slug = 'attribute-sets';

    public static function form(Form $form): Form
    {
        return $form->schema([...static::translated('name', required: true), TextInput::make('slug')->required()->unique(ignoreRecord: true), Select::make('attributes')->relationship('attributes', 'name')->getOptionLabelFromRecordUsing(fn ($record) => $record->name)->multiple()->preload(), Toggle::make('is_active')->default(true)])->columns(2);
    }

    public static function getPages(): array
    {
        return ['index' => AttributeSetResource\Pages\ManageRecords::route('/')];
    }
}
