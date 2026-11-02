<?php

namespace App\Filament\Resources;

use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

abstract class MarketingResource extends CommerceResource
{
    protected static string $permission = 'marketing.manage';

    public static function getNavigationGroup(): ?string
    {
        return __('marketing.marketing');
    }

    public static function getModelLabel(): string
    {
        return __('marketing.'.static::getSlug());
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id'), TextColumn::make('name')->label(__('marketing.name'))->formatStateUsing(fn ($record) => $record->name), IconColumn::make('is_active')->label(__('catalog.is_active'))->boolean()])->actions([EditAction::make(), DeleteAction::make()])->bulkActions([DeleteBulkAction::make()]);
    }
}
