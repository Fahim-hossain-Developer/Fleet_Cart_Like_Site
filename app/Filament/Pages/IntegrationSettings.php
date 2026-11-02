<?php

namespace App\Filament\Pages;

use App\Domain\Settings\Actions\CredentialVault;
use App\Domain\Settings\Actions\IntegrationConfiguration;
use App\Domain\Settings\Actions\Settings;
use App\Models\Admin;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** @property Form $form */
class IntegrationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.integration-settings';

    protected static ?string $navigationIcon = 'heroicon-o-key';

    public string $driver = 'fake';

    /** @var array<string,mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $a = auth('admin')->user();

        return $a instanceof Admin && $a->is_active && $a->hasPermissionTo('integrations.manage', 'admin');
    }

    public static function getNavigationLabel(): string
    {
        return __('integrations.settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('checkout.commerce');
    }

    public function getTitle(): string
    {
        return __('integrations.settings');
    }

    public function mount(): void
    {
        $this->loadDriver();
    }

    public function updatedDriver(): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(isset(IntegrationConfiguration::PROVIDERS[$this->driver]), 404);
        $this->loadDriver();
    }

    public function loadDriver(): void
    {
        $c = app(IntegrationConfiguration::class)->connection($this->driver);
        $this->form->fill(['is_enabled' => $c->is_enabled, 'mode' => $c->mode, ...$c->config, 'credentials' => [], 'cod_advance_mode' => store_setting('cod_advance_mode', 'off'), 'cod_advance_value' => store_setting('cod_advance_value', 0), 'sms_driver' => store_setting('sms_driver', 'sms_log'), 'admin_otp_roles' => store_setting('admin_otp_roles', [])]);
    }

    public function form(Form $form): Form
    {
        $schema = [Toggle::make('is_enabled')->label(__('checkout.enabled')), Select::make('mode')->label(__('integrations.mode'))->options(['sandbox' => __('integrations.sandbox'), 'live' => __('integrations.live')])->required(), TextInput::make('documentation_url')->label(__('integrations.documentation_url'))->url()->maxLength(2048), TextInput::make('minimum')->label(__('integrations.minimum'))->integer()->required()->minValue(1), TextInput::make('maximum')->label(__('integrations.maximum'))->integer()->required()->minValue(1)];
        if ($this->driver === 'sms_custom') {
            foreach (['endpoint', 'recipient_field', 'message_field', 'sender'] as $f) {
                $schema[] = TextInput::make($f)->label(__('integrations.'.$f));
            }
        }
        if ($this->driver === 'sms_custom') {
            $schema[] = Select::make('format')->label(__('integrations.format'))->options(['json' => 'JSON', 'form' => 'Form'])->default('json');
        }
        foreach (IntegrationConfiguration::PROVIDERS[$this->driver]['keys'] ?? [] as $key) {
            $schema[] = TextInput::make('credentials.'.$key)->label(__('integrations.credential_'.$key))->password()->autocomplete('new-password')->maxLength(32000)->helperText(__('integrations.blank_preserves'));
        }
        $schema[] = Section::make(__('integrations.store_rules'))->schema([Select::make('cod_advance_mode')->label(__('integrations.advance_mode'))->options(collect(['off', 'fixed', 'percent', 'delivery'])->mapWithKeys(fn ($v) => [$v => __('integrations.advance_'.$v)])->all())->required(), TextInput::make('cod_advance_value')->label(__('integrations.advance_value'))->integer()->minValue(0)->maxValue(100000000000)->required(), Select::make('sms_driver')->label(__('integrations.sms_driver'))->options(collect(['sms_log', 'sms_twilio', 'sms_custom'])->mapWithKeys(fn ($v) => [$v => __('integrations.provider_'.$v)])->all())->required(), Select::make('admin_otp_roles')->label(__('integrations.admin_roles'))->multiple()->options(Role::where('guard_name', 'admin')->pluck('name', 'name')->all())]);

        return $form->schema($schema)->statePath('data');
    }

    public function submit(): void
    {
        abort_unless(static::canAccess(), 403);
        try {
            $d = $this->form->getState();
            validator($d, ['admin_otp_roles' => 'array', 'admin_otp_roles.*' => 'string|exists:roles,name,guard_name,admin', 'sms_driver' => 'required|in:sms_log,sms_twilio,sms_custom', 'cod_advance_mode' => 'required|in:off,fixed,percent,delivery', 'cod_advance_value' => 'required|integer|min:0|max:100000000000'])->validate();
            if (($d['cod_advance_mode'] === 'percent') && $d['cod_advance_value'] > 10000) {
                throw ValidationException::withMessages(['data.cod_advance_value' => __('orders.invalid_operation')]);
            }
            if ($d['admin_otp_roles'] && ! app()->environment('local', 'testing') && in_array(config('mail.default'), ['log', 'array'], true)) {
                throw ValidationException::withMessages(['data.admin_otp_roles' => __('integrations.real_mail_required')]);
            }
            app(IntegrationConfiguration::class)->save($this->driver, $d, $d['credentials'] ?? []);
            foreach (['cod_advance_mode', 'cod_advance_value', 'sms_driver', 'admin_otp_roles'] as $key) {
                app(Settings::class)->set($key, $d[$key]);
            }
            Notification::make()->title(__('checkout.saved'))->success()->send();
        } finally {
            $this->data['credentials'] = [];
        }
    }

    /** @return array<string,string> */
    public function masks(): array
    {
        $c = app(IntegrationConfiguration::class);
        $out = [];
        foreach (IntegrationConfiguration::PROVIDERS[$this->driver]['keys'] ?? [] as $k) {
            $out[$k] = app(CredentialVault::class)->masked($c->secretKey($this->driver, $k));
        }

        return $out;
    }
}
