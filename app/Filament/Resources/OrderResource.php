<?php

namespace App\Filament\Resources;

use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends CommerceResource
{
    protected static ?string $model = Order::class;

    protected static string $permission = 'orders.manage';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->label(__('orders.number'))->searchable(), TextColumn::make('guest_email')->label(__('orders.email'))->searchable()->toggleable(isToggledHiddenByDefault: true), TextColumn::make('phone')->label(__('orders.phone'))->searchable(), TextColumn::make('status')->label(__('orders.status'))->formatStateUsing(fn ($state) => __('orders.status_'.$state))->badge(), TextColumn::make('payment_status')->label(__('orders.payment_status'))->formatStateUsing(fn ($state) => __('orders.payment_'.$state)), TextColumn::make('total')->label(__('orders.total'))->formatStateUsing(fn ($state, $record) => $record->currency.' '.number_format($state / 100, 2)), TextColumn::make('risk_flags')->label(__('orders.risk_flags'))->badge()->formatStateUsing(fn ($state) => __('orders.risk_'.$state)), TextColumn::make('placed_at')->label(__('orders.placed_at'))->dateTime(),
        ])->filters([SelectFilter::make('user_id')->label(__('orders.customer'))->options(fn () => User::orderBy('id')->limit(500)->pluck('name', 'id')), SelectFilter::make('status')->label(__('orders.status'))->options(array_combine(array_keys(ManageOrder::TRANSITIONS), array_map(fn ($s) => __('orders.status_'.$s), array_keys(ManageOrder::TRANSITIONS)))), SelectFilter::make('payment_status')->label(__('orders.payment_status'))->options(collect(['unpaid', 'partial', 'paid', 'partial_refund', 'refunded'])->mapWithKeys(fn ($s) => [$s => __('orders.payment_'.$s)])->all())])->actions([ViewAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => OrderResource\Pages\ListOrders::route('/'), 'create' => OrderResource\Pages\CreateOrder::route('/create'), 'view' => OrderResource\Pages\ViewOrder::route('/{record}')];
    }
}
