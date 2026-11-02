<?php

namespace App\Filament\Pages;

use App\Domain\Settings\Actions\Settings;
use App\Models\Admin;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** @property Form $form */
class CheckoutPreferences extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.checkout-preferences';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    /** @var array<string,mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('checkout.commerce');
    }

    public static function getNavigationLabel(): string
    {
        return __('checkout.preferences');
    }

    public function getTitle(): string
    {
        return __('checkout.preferences');
    }

    public static function canAccess(): bool
    {
        $a = auth('admin')->user();

        return $a instanceof Admin && $a->is_active && $a->hasPermissionTo('checkout.manage', 'admin');
    }

    public function mount(): void
    {
        $this->form->fill(['orders_stock_policy' => store_setting('orders_stock_policy', 'placement'), 'orders_high_value_cod' => store_setting('orders_high_value_cod', 1000000), 'checkout_order_prefix' => store_setting('checkout_order_prefix', 'ORD'), 'checkout_mode' => store_setting('checkout_mode', 'one_page'), 'checkout_guest_enabled' => store_setting('checkout_guest_enabled', true), 'checkout_gift_enabled' => store_setting('checkout_gift_enabled', true), 'checkout_gift_fee' => store_setting('checkout_gift_fee', 5000), 'checkout_gift_tax_bps' => store_setting('checkout_gift_tax_bps', 0), 'checkout_auto_account' => store_setting('checkout_auto_account', false), 'checkout_payment_methods' => store_setting('checkout_payment_methods', config('checkout.payment_methods'))]);
    }

    public function form(Form $form): Form
    {
        $schema = [Select::make('orders_stock_policy')->label(__('orders.stock_policy'))->options(['placement' => __('orders.placement'), 'confirmation' => __('orders.confirmation'), 'payment' => __('orders.payment')])->required(), TextInput::make('orders_high_value_cod')->label(__('orders.high_value_threshold'))->integer()->minValue(1)->maxValue(100000000000)->required(), TextInput::make('checkout_order_prefix')->label(__('checkout.order_prefix'))->required()->maxLength(12)->regex('/^[A-Z0-9-]+$/D'), Select::make('checkout_mode')->label(__('checkout.mode'))->options(['one_page' => __('checkout.one_page'), 'steps' => __('checkout.steps')])->required(), Toggle::make('checkout_guest_enabled')->label(__('checkout.guest_enabled')), Toggle::make('checkout_gift_enabled')->label(__('checkout.make_gift')), TextInput::make('checkout_gift_fee')->label(__('checkout.gift_fee'))->integer()->minValue(0)->maxValue(100000000)->required(), TextInput::make('checkout_gift_tax_bps')->label(__('checkout.gift_tax_bps'))->integer()->minValue(0)->maxValue(10000)->required(), Toggle::make('checkout_auto_account')->label(__('checkout.auto_account'))];
        foreach (['cod', 'bank', 'manual_mobile'] as $method) {
            $schema[] = Section::make(__('checkout.'.$method))->schema([Toggle::make('checkout_payment_methods.'.$method.'.enabled')->label(__('checkout.enabled')), Textarea::make('checkout_payment_methods.'.$method.'.instructions')->label(__('checkout.instructions'))->maxLength(2000)]);
        }

        return $form->schema($schema)->statePath('data');
    }

    public function submit(): void
    {
        abort_unless(static::canAccess(), 403);
        foreach ($this->form->getState() as $key => $value) {
            app(Settings::class)->set($key, $value);
        } Notification::make()->title(__('checkout.saved'))->success()->send();
    }
}
