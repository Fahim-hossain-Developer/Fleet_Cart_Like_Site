<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\LandingTemplate;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LandingTemplateResource extends CommerceResource
{
    protected static ?string $model = LandingTemplate::class;

    protected static ?string $slug = 'landing-templates';

    protected static string $permission = 'landing.manage';

    public static function getNavigationGroup(): ?string
    {
        return __('appearance.appearance');
    }

    public static function getModelLabel(): string
    {
        return __('landing.templates');
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label(__('marketing.name'))->formatStateUsing(fn ($record) => $record->name[app()->getLocale()] ?? $record->name['en']), TextColumn::make('slug'), IconColumn::make('is_system')->boolean()])->actions([Action::make('export')->label(__('landing.export_template'))->url(fn ($record) => route('landing.template.export', $record->id)), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => LandingTemplateResource\Pages\ManageRecords::route('/')];
    }
}
