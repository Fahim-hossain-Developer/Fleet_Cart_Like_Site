<?php

namespace App\Filament\Pages;

use App\Domain\Settings\Actions\CredentialVault;
use App\Domain\Settings\Actions\Settings;
use App\Models\Admin;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** @property Form $form */
class TrackingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.tracking-settings';

    /** @var array<string,mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $a = auth('admin')->user();

        return $a instanceof Admin && $a->is_active && $a->hasRole('super-admin');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketing.tracking_settings');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function mount(): void
    {
        $this->form->fill(['enabled' => store_setting('tracking_enabled', false), 'capi' => store_setting('tracking_capi', false), 'ids' => store_setting('tracking_ids', [])]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([Toggle::make('enabled')->label(__('marketing.tracking_enable')), Toggle::make('capi')->label(__('marketing.capi_enable')), TextInput::make('ids.ga4')->label('GA4')->maxLength(22), TextInput::make('ids.gtm')->label('GTM')->maxLength(24), TextInput::make('ids.meta')->label('Meta Pixel')->maxLength(25), TextInput::make('ids.tiktok')->label('TikTok Pixel')->maxLength(30), TextInput::make('token')->label(__('marketing.capi_token'))->password()->autocomplete('off')->maxLength(4096)->placeholder(app(CredentialVault::class)->masked('tracking.meta_token'))])->statePath('data');
    }

    public function submit(): void
    {
        abort_unless(static::canAccess(), 403);
        $d = $this->form->getState();
        app(Settings::class)->set('tracking_ids', $d['ids'] ?? []);
        app(Settings::class)->set('tracking_enabled', (bool) ($d['enabled'] ?? false));
        app(Settings::class)->set('tracking_capi', (bool) ($d['capi'] ?? false));
        if (filled($d['token'] ?? null)) {
            app(CredentialVault::class)->replace('tracking.meta_token', trim($d['token']));
        }
        activity()->causedBy(auth('admin')->user())->log('tracking_settings_changed');
        $this->mount();
        Notification::make()->title(__('orders.saved'))->success()->send();
    }
}
