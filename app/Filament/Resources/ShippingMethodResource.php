<?php

namespace App\Filament\Resources;

use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingZone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class ShippingMethodResource extends CommerceResource
{
    protected static ?string $model = ShippingMethod::class;

    protected static ?string $slug = 'shipping-methods';

    protected static string $permission = 'shipping.manage';

    public static function form(Form $form): Form
    {
        return $form->schema([...static::names(), Select::make('shipping_zone_id')->options(fn () => ShippingZone::all()->mapWithKeys(fn ($z) => [$z->id => $z->name['en']])->all())->required(), Select::make('type')->options(['flat' => __('checkout.flat'), 'weight' => __('checkout.weight'), 'pickup' => __('checkout.pickup')])->default('flat')->required(), TextInput::make('amount')->integer()->minValue(0)->maxValue(100000000)->default(0)->required(), TextInput::make('free_above')->integer()->minValue(0)->maxValue(100000000000), TextInput::make('base_weight_grams')->integer()->minValue(1)->default(1000), TextInput::make('step_weight_grams')->integer()->minValue(1)->default(1000), TextInput::make('step_amount')->integer()->minValue(0)->maxValue(100000000)->default(0), Toggle::make('is_active')->default(true)])->columns(2);
    }

    public static function getPages(): array
    {
        return ['index' => ShippingMethodResource\Pages\ManageRecords::route('/')];
    }
}
