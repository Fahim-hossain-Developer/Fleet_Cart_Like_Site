<?php

namespace App\Filament\Pages;

use App\Domain\Settings\Actions\Settings;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** @property Form $form */
class CatalogPreferences extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.catalog-preferences';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    /** @var array<string,mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('catalog.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('catalog.catalog_preferences');
    }

    public function getTitle(): string
    {
        return __('catalog.catalog_preferences');
    }

    public static function canAccess(): bool
    {
        return CatalogImports::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill(['catalog_top_bar' => store_setting('catalog_top_bar', true), 'catalog_max_badges' => store_setting('catalog_max_badges', 2), 'catalog_new_days' => store_setting('catalog_new_days', 30), 'catalog_clone_keep_stock' => store_setting('catalog_clone_keep_stock', false), 'catalog_sidebar' => store_setting('catalog_sidebar', ['categories', 'attributes', 'latest', 'sale', 'top_rated', 'tags'])]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([Toggle::make('catalog_top_bar')->label(__('catalog.top_bar')), TextInput::make('catalog_max_badges')->label(__('catalog.max_badges'))->integer()->minValue(0)->maxValue(10)->required(), TextInput::make('catalog_new_days')->label(__('catalog.new_days'))->integer()->minValue(0)->maxValue(365)->required(), Toggle::make('catalog_clone_keep_stock')->label(__('catalog.keep_stock')), CheckboxList::make('catalog_sidebar')->label(__('catalog.sidebar'))->options(collect(['categories', 'attributes', 'latest', 'sale', 'top_rated', 'tags'])->mapWithKeys(fn ($v) => [$v => __('catalog.'.$v)])->all())])->statePath('data');
    }

    public function submit(): void
    {
        abort_unless(static::canAccess(), 403);
        foreach ($this->form->getState() as $key => $value) {
            app(Settings::class)->set($key, $value);
        } Notification::make()->title(__('catalog.saved'))->success()->send();
    }
}
