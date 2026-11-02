<?php

namespace App\Filament\Resources;

use App\Domain\Shipping\Models\Location;
use App\Domain\Shipping\Models\ShippingZone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class ShippingZoneResource extends CommerceResource
{
    protected static ?string $model = ShippingZone::class;

    protected static ?string $slug = 'shipping-zones';

    protected static string $permission = 'shipping.manage';

    public static function form(Form $form): Form
    {
        return $form->schema([...static::names(), TextInput::make('country')->required()->length(2)->default('BD'), Select::make('district_ids')->label(__('checkout.district'))->options(fn () => Location::where('type', 'district')->get()->mapWithKeys(fn ($l) => [$l->id => $l->name[app()->getLocale()] ?? $l->name['en']])->all())->multiple()->required(), Toggle::make('is_active')->default(true)])->columns(2);
    }

    public static function getPages(): array
    {
        return ['index' => ShippingZoneResource\Pages\ManageRecords::route('/')];
    }
}
