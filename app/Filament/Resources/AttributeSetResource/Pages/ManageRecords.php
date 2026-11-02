<?php

namespace App\Filament\Resources\AttributeSetResource\Pages;

use App\Filament\Resources\AttributeSetResource;
use Filament\Actions\CreateAction;

class ManageRecords extends \Filament\Resources\Pages\ManageRecords
{
    protected static string $resource = AttributeSetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
