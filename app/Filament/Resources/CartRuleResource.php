<?php

namespace App\Filament\Resources;

use App\Domain\Marketing\Models\CartRule;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class CartRuleResource extends MarketingResource
{
    protected static ?string $model = CartRule::class;

    protected static ?string $slug = 'cart-rules';

    public static function form(Form $form): Form
    {
        return $form->schema([...static::names(), Toggle::make('is_active')->label(__('catalog.is_active'))->default(false), TextInput::make('priority')->label(__('marketing.priority'))->integer()->minValue(0)->maxValue(100000)->default(100)->required(), TextInput::make('minimum')->label(__('marketing.minimum'))->integer()->minValue(0)->maxValue(100000000000)->default(0)->required(), TextInput::make('minimum_qty')->label(__('marketing.minimum_qty'))->integer()->minValue(1)->maxValue(10000)->default(1)->required(), Select::make('type')->label(__('content.type'))->options(['fixed' => __('checkout.fixed'), 'percent' => __('checkout.percent'), 'free_shipping' => __('checkout.free_shipping')])->default('fixed')->required(), TextInput::make('value')->label(__('marketing.value'))->integer()->minValue(0)->maxValue(100000000000)->default(0)->required(), TextInput::make('max_discount')->label(__('marketing.max_discount'))->integer()->minValue(0)->maxValue(100000000000), Toggle::make('stackable')->label(__('marketing.stackable'))->default(false), DateTimePicker::make('starts_at')->label(__('content.starts_at')), DateTimePicker::make('ends_at')->label(__('content.ends_at'))->after('starts_at')])->columns(2);
    }

    public static function getPages(): array
    {
        return ['index' => CartRuleResource\Pages\ManageRecords::route('/')];
    }
}
