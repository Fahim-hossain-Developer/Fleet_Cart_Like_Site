<?php

namespace App\Filament\Resources;

use App\Models\Admin;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

abstract class CommerceResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string $permission = 'checkout.manage';

    public static function getNavigationGroup(): ?string
    {
        return __('checkout.commerce');
    }

    public static function getModelLabel(): string
    {
        return __('checkout.'.static::getSlug());
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function canViewAny(): bool
    {
        $a = auth('admin')->user();

        return $a instanceof Admin && $a->is_active && $a->hasPermissionTo(static::$permission, 'admin');
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    /** @return array<Component> */
    public static function names(): array
    {
        return [TextInput::make('name.en')->label(__('checkout.name').' (English)')->required()->maxLength(100), TextInput::make('name.bn')->label(__('checkout.name').' (বাংলা)')->maxLength(100)];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id')->label('#'), TextColumn::make('name')->label(__('checkout.name'))->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? $state['en']) : $state)])->actions([EditAction::make()]);
    }
}
