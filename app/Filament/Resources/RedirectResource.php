<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\Redirect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RedirectResource extends CommerceResource
{
    protected static ?string $model = Redirect::class;

    protected static ?string $slug = 'redirects';

    protected static string $permission = 'settings.manage';

    public static function getModelLabel(): string
    {
        return __('marketing.redirects');
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('from')->required()->maxLength(255)->unique(ignoreRecord: true), TextInput::make('to')->required()->maxLength(2048), Select::make('code')->options([301 => '301', 302 => '302'])->default(301)->required()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('from')->searchable(), TextColumn::make('to'), TextColumn::make('code')])->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => RedirectResource\Pages\ManageRecords::route('/')];
    }
}
