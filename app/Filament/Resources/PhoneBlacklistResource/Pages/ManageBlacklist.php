<?php

namespace App\Filament\Resources\PhoneBlacklistResource\Pages;

use App\Filament\Resources\PhoneBlacklistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBlacklist extends ManageRecords
{
    protected static string $resource = PhoneBlacklistResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
