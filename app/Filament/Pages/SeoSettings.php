<?php

namespace App\Filament\Pages;

use App\Domain\Orders\Actions\OrderAccess;
use App\Domain\Settings\Actions\Settings;
use App\Models\Admin;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** @property Form $form */
class SeoSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.seo-settings';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    /** @var array<string,mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $a = auth('admin')->user();

        return $a instanceof Admin && $a->is_active && $a->hasPermissionTo('settings.manage', 'admin');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('marketing.marketing');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketing.seo_settings');
    }

    public function getTitle(): string
    {
        return __('marketing.seo_settings');
    }

    public function mount(): void
    {
        $this->form->fill(['seo_defaults' => store_setting('seo_defaults', ['schema' => true])]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('seo_defaults.title.en')->maxLength(200), TextInput::make('seo_defaults.title.bn')->maxLength(200), Textarea::make('seo_defaults.description.en')->maxLength(500), Textarea::make('seo_defaults.description.bn')->maxLength(500), Toggle::make('seo_defaults.schema')->label(__('marketing.enable_schema'))])->statePath('data');
    }

    public function submit(): void
    {
        $a = app(OrderAccess::class)->staff('settings.manage');
        $data = $this->form->getState();
        foreach ($data as $key => $value) {
            app(Settings::class)->set($key, $value);
        }activity()->causedBy($a)->withProperties(['keys' => array_keys($data)])->log('seo_settings_changed');
        Notification::make()->title(__('orders.saved'))->success()->send();
    }
}
