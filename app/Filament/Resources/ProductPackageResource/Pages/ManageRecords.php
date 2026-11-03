<?php

namespace App\Filament\Resources\ProductPackageResource\Pages;

use App\Filament\Resources\ProductPackageResource;
use Filament\Actions\CreateAction;

class ManageRecords extends \Filament\Resources\Pages\ManageRecords
{
    protected static string $resource = ProductPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
