<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [CreateAction::make(), Action::make('export')->label(__('orders.export_csv'))->visible(fn () => auth('admin')->user()->can('orders.export'))->url(route('commerce.export', 'orders'))];
    }

    protected static string $resource = OrderResource::class;
}
