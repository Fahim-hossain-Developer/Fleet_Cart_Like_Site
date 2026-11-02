<?php

namespace App\Filament\Resources;

use App\Domain\Orders\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderResource extends CommerceResource
{
    protected static ?string $model = Order::class;

    protected static string $permission = 'orders.manage';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns(array_map(fn ($key) => TextColumn::make($key)->label(__('checkout.'.$key))->searchable(), ['number', 'phone', 'payment_method', 'status', 'payment_status', 'total', 'gift_fee']))->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => OrderResource\Pages\ListOrders::route('/')];
    }
}
