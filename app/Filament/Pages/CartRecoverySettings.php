<?php

namespace App\Filament\Pages;

use App\Domain\Orders\Actions\OrderAccess;
use App\Domain\Settings\Actions\Settings;
use App\Models\Admin;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** @property Form $form */
class CartRecoverySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.cart-recovery-settings';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    /** @var array<string,mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $a = auth('admin')->user();

        return $a instanceof Admin && $a->is_active && $a->hasPermissionTo('marketing.manage', 'admin');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('marketing.marketing');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketing.cart_recovery');
    }

    public function getTitle(): string
    {
        return __('marketing.cart_recovery');
    }

    public function mount(): void
    {
        $this->form->fill(['recovery_enabled' => store_setting('recovery_enabled', false)]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([Toggle::make('recovery_enabled')->label(__('marketing.recovery_enable'))->helperText(__('marketing.recovery_consent_help'))])->statePath('data');
    }

    public function submit(): void
    {
        $a = app(OrderAccess::class)->staff('marketing.manage');
        $data = $this->form->getState();
        foreach ($data as $key => $value) {
            app(Settings::class)->set($key, $value);
        }activity()->causedBy($a)->withProperties(['keys' => array_keys($data)])->log('cart_recovery_settings_changed');
        Notification::make()->title(__('orders.saved'))->success()->send();
    }
}
