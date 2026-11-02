<?php

namespace App\Filament\Resources;

use App\Domain\Settings\Models\Currency;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CurrencyResource extends CommerceResource
{
    protected static ?string $model = Currency::class;

    protected static ?string $slug = 'currencies';

    protected static string $permission = 'checkout.manage';

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('code')->required()->regex('/^[A-Z]{3}$/D')->unique(ignoreRecord: true), TextInput::make('symbol')->required()->maxLength(10), TextInput::make('rate_ppm')->label(__('checkout.rate_ppm'))->integer()->minValue(1)->maxValue(1000000000)->required()->default(1000000), TextInput::make('decimals')->integer()->minValue(0)->maxValue(3)->default(2)->required(), Select::make('symbol_position')->options(['before' => __('checkout.before'), 'after' => __('checkout.after')])->default('before')->required(), TextInput::make('rounding_increment')->integer()->minValue(1)->maxValue(10000)->default(1)->required(), TextInput::make('markup_bps')->integer()->minValue(0)->maxValue(10000)->default(0)->required(), Toggle::make('is_active')->default(true)])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns(array_map(fn ($key) => TextColumn::make($key)->label(__('checkout.'.$key)), ['id', 'code', 'type', 'rate_ppm']))->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => CurrencyResource\Pages\ManageRecords::route('/')];
    }
}
