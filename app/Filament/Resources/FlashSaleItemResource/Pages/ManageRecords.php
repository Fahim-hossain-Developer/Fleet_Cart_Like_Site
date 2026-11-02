<?php

namespace App\Filament\Resources\FlashSaleItemResource\Pages;

use App\Filament\Resources\FlashSaleItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords as BaseManageRecords;

class ManageRecords extends BaseManageRecords
{
    protected static string $resource = FlashSaleItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
