<?php

namespace App\Filament\Resources;

use App\Domain\Customers\Models\BlacklistedPhone;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PhoneBlacklistResource extends CommerceResource
{
    protected static ?string $model = BlacklistedPhone::class;

    protected static string $permission = 'customers.manage';

    public static function getModelLabel(): string
    {
        return __('orders.blacklist');
    }

    public static function getPluralModelLabel(): string
    {
        return __('orders.blacklist');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('phone')->label(__('orders.phone'))->required()->regex('/^\+8801[3-9][0-9]{8}$/D')->unique(ignoreRecord: true), TextInput::make('reason')->label(__('orders.reason'))->required()->maxLength(255)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('phone')->label(__('orders.phone'))->searchable(), TextColumn::make('reason')->label(__('orders.reason'))])->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => PhoneBlacklistResource\Pages\ManageBlacklist::route('/')];
    }
}
