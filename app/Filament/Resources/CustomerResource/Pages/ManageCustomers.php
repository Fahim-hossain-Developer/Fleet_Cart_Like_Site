<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomers extends ManageRecords
{
    protected function getHeaderActions(): array
    {
        return [Action::make('export')->label(__('orders.export_csv'))->visible(fn () => auth('admin')->user()->can('customers.export'))->url(route('commerce.export', 'customers'))];
    }

    protected static string $resource = CustomerResource::class;
}
