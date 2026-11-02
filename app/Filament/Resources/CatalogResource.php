<?php

namespace App\Filament\Resources;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

abstract class CatalogResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    public static function getNavigationGroup(): ?string
    {
        return __('catalog.catalog');
    }

    public static function getModelLabel(): string
    {
        return __('catalog.'.static::getSlug());
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    /** @return array<Component> */
    public static function translated(string $key, bool $rich = false, bool $required = false): array
    {
        $class = $rich ? RichEditor::class : TextInput::class;

        return [$class::make($key.'.en')->label(__('catalog.'.$key).' (English)')->required($required), $class::make($key.'.bn')->label(__('catalog.'.$key).' (বাংলা)')];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label(__('catalog.name'))->searchable()->formatStateUsing(fn ($record) => $record->name), TextColumn::make('slug')->label(__('catalog.slug'))->searchable()])->actions([EditAction::make()])->bulkActions([DeleteBulkAction::make()]);
    }
}
