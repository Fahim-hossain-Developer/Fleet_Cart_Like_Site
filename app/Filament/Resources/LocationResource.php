<?php

namespace App\Filament\Resources;

use App\Domain\Shipping\Models\Location;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

class LocationResource extends CommerceResource
{
    protected static ?string $model = Location::class;

    protected static ?string $slug = 'locations';

    protected static string $permission = 'shipping.manage';

    public static function form(Form $form): Form
    {
        return $form->schema([...static::names(), TextInput::make('code')->required()->unique(ignoreRecord: true), TextInput::make('country')->length(2)->default('BD')->required(), Select::make('type')->options(['division' => __('checkout.division'), 'district' => __('checkout.district'), 'upazila' => __('checkout.upazila'), 'area' => __('checkout.area')])->required(), Select::make('parent_id')->options(fn () => Location::whereIn('type', ['division', 'district', 'upazila'])->get()->mapWithKeys(fn ($l) => [$l->id => $l->name['en']])->all())->searchable()])->columns(2);
    }

    public static function getPages(): array
    {
        return ['index' => LocationResource\Pages\ManageRecords::route('/')];
    }
}
