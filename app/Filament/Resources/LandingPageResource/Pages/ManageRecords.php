<?php

namespace App\Filament\Resources\LandingPageResource\Pages;

use App\Domain\Content\Landing\LandingRules;
use App\Domain\Content\Landing\LandingTemplates;
use App\Filament\Resources\LandingPageResource;
use Filament\Actions\CreateAction;

class ManageRecords extends \Filament\Resources\Pages\ManageRecords
{
    protected static string $resource = LandingPageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->fillForm(fn () => ['product_id' => (int) request()->query('product', 0) ?: null, 'sections' => app(LandingTemplates::class)->starters()[0]['sections'], 'form_config' => app(LandingRules::class)->formDefaults(), 'status' => 'draft', 'is_active' => true, 'sort' => 0])->mutateFormDataUsing(function (array $data): array {
            if (empty($data['sections'])) {
                $data['sections'] = app(LandingTemplates::class)->starters()[0]['sections'];
            }

            return $data;
        })];
    }
}
