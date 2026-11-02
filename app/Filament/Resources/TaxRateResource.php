<?php

namespace App\Filament\Resources;

use App\Domain\Checkout\Models\TaxClass;
use App\Domain\Checkout\Models\TaxRate;
use App\Domain\Shipping\Models\Location;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaxRateResource extends CommerceResource
{
    protected static ?string $model = TaxRate::class;

    protected static ?string $slug = 'tax-rates';

    protected static string $permission = 'checkout.manage';

    public static function form(Form $form): Form
    {
        return $form->schema([Select::make('tax_class_id')->options(fn () => TaxClass::all()->mapWithKeys(fn ($t) => [$t->id => $t->name['en']])->all())->required(), TextInput::make('country')->default('BD')->required()->length(2), Select::make('district_id')->options(fn () => Location::where('type', 'district')->get()->mapWithKeys(fn ($l) => [$l->id => $l->name['en']])->all())->searchable(), TextInput::make('rate_bps')->label(__('checkout.rate_bps'))->integer()->minValue(0)->maxValue(10000)->required()->default(0), Toggle::make('inclusive'), Toggle::make('is_active')->default(true)])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns(array_map(fn ($key) => TextColumn::make($key)->label(__('checkout.'.$key)), ['id', 'country', 'rate_bps', 'inclusive']))->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => TaxRateResource\Pages\ManageRecords::route('/')];
    }
}
