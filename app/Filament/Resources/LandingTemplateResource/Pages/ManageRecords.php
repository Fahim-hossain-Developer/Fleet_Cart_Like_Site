<?php

namespace App\Filament\Resources\LandingTemplateResource\Pages;

use App\Domain\Content\Landing\LandingTemplates;
use App\Filament\Resources\LandingTemplateResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;

class ManageRecords extends \Filament\Resources\Pages\ManageRecords
{
    protected static string $resource = LandingTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('import')->label(__('landing.import_template'))->form([Textarea::make('json')->label('JSON')->required()->maxLength(262144)->rows(12)])->action(fn ($data) => app(LandingTemplates::class)->import($data['json']))];
    }
}
