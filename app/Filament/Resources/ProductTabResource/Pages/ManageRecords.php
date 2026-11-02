<?php

namespace App\Filament\Resources\ProductTabResource\Pages;

use App\Filament\Resources\ProductTabResource;
use Filament\Actions\CreateAction;

class ManageRecords extends \Filament\Resources\Pages\ManageRecords
{
    protected static string $resource = ProductTabResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
