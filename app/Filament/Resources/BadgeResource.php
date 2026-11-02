<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Badge;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BadgeResource extends CatalogResource
{
    protected static ?string $model = Badge::class;

    protected static ?string $slug = 'badges';

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('key')->required()->regex('/^[a-z0-9-]+$/')->unique(ignoreRecord: true), ...static::translated('label', required: true), ColorPicker::make('color')->required()->default('#065f46')->regex('/^#[0-9a-fA-F]{6}$/'), TextInput::make('icon')->maxLength(50), TextInput::make('priority')->numeric()->minValue(0)->default(50), DateTimePicker::make('starts_at'), DateTimePicker::make('ends_at')->afterOrEqual('starts_at'), Toggle::make('is_active')->default(true), Select::make('products')->relationship('products', 'name')->getOptionLabelFromRecordUsing(fn ($record) => $record->name)->multiple()->searchable()->preload(), Select::make('categories')->relationship('categories', 'name')->getOptionLabelFromRecordUsing(fn ($record) => $record->name)->multiple()->preload()])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('label')->label(__('catalog.label')), TextColumn::make('key')->label(__('catalog.key'))])->actions([EditAction::make()])->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => BadgeResource\Pages\ManageRecords::route('/')];
    }
}
