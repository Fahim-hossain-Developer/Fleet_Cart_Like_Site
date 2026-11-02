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
class SocialLoginSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.social-login-settings';

    /** @var array<string,mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $a = auth('admin')->user();

        return $a instanceof Admin && $a->is_active && $a->hasRole('super-admin');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketing.social_login');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function mount(): void
    {
        $d = [];
        foreach (['google', 'facebook'] as $p) {
            $d[$p] = ['enabled' => store_setting('social_login_'.$p, false)];
        }$this->form->fill($d);
    }

    public function form(Form $form): Form
    {
        $fields = [];
        foreach (['google', 'facebook'] as $p) {
            $fields[] = Toggle::make($p.'.enabled')->label(ucfirst($p));
            foreach (['client_id', 'client_secret'] as $key) {
                $fields[] = TextInput::make($p.'.'.$key)->label(ucfirst($p).' '.$key)->password()->autocomplete('off')->placeholder(app(CredentialVault::class)->masked('social.'.$p.'.'.$key))->maxLength(2048);
            }
        }

        return $form->schema($fields)->statePath('data');
    }

    public function submit(): void
    {
        abort_unless(static::canAccess(), 403);
        $d = $this->form->getState();
        foreach (['google', 'facebook'] as $p) {
            foreach (['client_id', 'client_secret'] as $key) {
                if (filled($d[$p][$key] ?? null)) {
                    app(CredentialVault::class)->replace('social.'.$p.'.'.$key, trim($d[$p][$key]));
                }
            }app(Settings::class)->set('social_login_'.$p, (bool) ($d[$p]['enabled'] ?? false));
        }
        activity()->causedBy(auth('admin')->user())->log('social_login_settings_changed');
        $this->mount();
        Notification::make()->title(__('orders.saved'))->success()->send();
    }
}
