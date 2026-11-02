<?php

namespace App\Filament\Resources;

use App\Domain\Marketing\Models\Coupon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CouponResource extends CommerceResource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $slug = 'coupons';

    protected static string $permission = 'marketing.manage';

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('code')->required()->regex('/^[A-Z0-9_-]+$/D')->maxLength(50)->unique(ignoreRecord: true), Select::make('type')->options(['fixed' => __('checkout.fixed'), 'percent' => __('checkout.percent'), 'free_shipping' => __('checkout.free_shipping')])->required()->default('fixed'), TextInput::make('value')->integer()->minValue(0)->maxValue(100000000000)->required(), TextInput::make('minimum')->integer()->minValue(0)->maxValue(100000000000)->default(0), TextInput::make('max_discount')->integer()->minValue(0)->maxValue(100000000000), TextInput::make('usage_limit')->integer()->minValue(1), TextInput::make('per_customer_limit')->integer()->minValue(1), DateTimePicker::make('starts_at'), DateTimePicker::make('ends_at')->afterOrEqual('starts_at'), Toggle::make('is_public')->label(__('marketing.publish_coupon'))->default(false), Toggle::make('is_active')->default(true)])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns(array_map(fn ($key) => TextColumn::make($key)->label(__('checkout.'.$key)), ['id', 'code', 'type', 'value', 'used']))->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => CouponResource\Pages\ManageRecords::route('/')];
    }
}
