<?php

namespace App\Filament\Pages;

use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Landing\LandingTemplates;
use App\Domain\Content\Models\LandingPage;
use App\Domain\Settings\Actions\Settings;
use App\Models\Admin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

/** @property Form $form */
class StoreMode extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.store-mode';

    /** @var array<string,mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $a = auth('admin')->user();

        return $a instanceof Admin && $a->is_active && $a->hasPermissionTo('landing.manage', 'admin');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('appearance.appearance');
    }

    public static function getNavigationLabel(): string
    {
        return __('landing.store_mode');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function mount(): void
    {
        $this->form->fill(['store_mode' => store_setting('store_mode', 'multi'), 'primary_landing_page' => store_setting('primary_landing_page'), 'single_show_catalog' => store_setting('single_show_catalog', false)]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([Select::make('store_mode')->label(__('landing.store_mode'))->options(['multi' => __('landing.multi'), 'single' => __('landing.single')])->required(), Select::make('primary_landing_page')->label(__('landing.home_landing'))->options(LandingPage::published()->get()->mapWithKeys(fn ($p) => [$p->id => $p->title])->all())->searchable(), Toggle::make('single_show_catalog')->label(__('landing.show_catalog'))])->statePath('data');
    }

    public function submit(): void
    {
        $a = app(LandingTemplates::class)->staff();
        $d = $this->form->getState();
        if ($d['store_mode'] === 'single') {
            $p = LandingPage::published()->whereKey($d['primary_landing_page'])->first();
            if (! $p || ! $p->packages()->where('role', 'primary')->where('is_active', true)->exists() || ! Product::published()->whereKey($p->product_id)->exists()) {
                throw ValidationException::withMessages(['primary_landing_page' => __('landing.package_unavailable')]);
            }
        }
        foreach ($d as $key => $value) {
            app(Settings::class)->set($key, $value);
        }activity()->causedBy($a)->log('store_mode_changed');
        Notification::make()->title(__('orders.saved'))->success()->send();
    }
}
