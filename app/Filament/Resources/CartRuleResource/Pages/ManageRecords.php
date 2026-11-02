<?php

namespace App\Filament\Resources\CartRuleResource\Pages;

use App\Filament\Resources\CartRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords as BaseManageRecords;

class ManageRecords extends BaseManageRecords
{
    protected static string $resource = CartRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
