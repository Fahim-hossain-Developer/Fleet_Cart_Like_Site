<?php

namespace App\Filament\Pages;

use App\Domain\Orders\Actions\OrderAccess;
use App\Domain\Settings\Actions\Settings;
use App\Models\Admin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** @property Form $form */
class StorefrontMarketing extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.storefront-marketing';

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
        return __('marketing.storefront_marketing');
    }

    public function getTitle(): string
    {
        return __('marketing.storefront_marketing');
    }

    public function mount(): void
    {
        $this->form->fill(['floating_controls' => store_setting('floating_controls', ['position' => 'right', 'paths' => [], 'exclude' => [], 'whatsapp' => ['enabled' => false], 'messenger' => ['enabled' => false], 'call' => ['enabled' => false], 'back_to_top' => false, 'chat' => ['enabled' => false]]), 'social_proof' => store_setting('social_proof', ['enabled' => false, 'frequency' => 60, 'exclude' => []])]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('floating_controls.position')->options(['left' => __('marketing.left'), 'right' => __('marketing.right')])->required(),
            TagsInput::make('floating_controls.paths')->helperText(__('marketing.paths_help')),
            TagsInput::make('floating_controls.exclude'),
            Toggle::make('floating_controls.whatsapp.enabled')->label('WhatsApp'),
            TextInput::make('floating_controls.whatsapp.phone')->label(__('marketing.international_phone'))->maxLength(16),
            TextInput::make('floating_controls.whatsapp.message')->maxLength(300),
            Toggle::make('floating_controls.messenger.enabled')->label('Messenger'),
            TextInput::make('floating_controls.messenger.page')->maxLength(100),
            Toggle::make('floating_controls.call.enabled')->label(__('marketing.control_call')),
            TextInput::make('floating_controls.call.phone')->label(__('marketing.international_phone'))->maxLength(16),
            Toggle::make('floating_controls.back_to_top')->label(__('marketing.back_to_top')),
            Toggle::make('floating_controls.chat.enabled')->label('Tawk.to'),
            TextInput::make('floating_controls.chat.property')->maxLength(24),
            TextInput::make('floating_controls.chat.widget')->maxLength(30),
            Toggle::make('social_proof.enabled')->label(__('marketing.recent_purchases')),
            TextInput::make('social_proof.frequency')->integer()->minValue(30)->maxValue(3600)->required(),
            TagsInput::make('social_proof.exclude'),
        ])->statePath('data');
    }

    public function submit(): void
    {
        $a = app(OrderAccess::class)->staff('marketing.manage');
        $data = $this->form->getState();
        foreach ($data as $key => $value) {
            app(Settings::class)->set($key, $value);
        }activity()->causedBy($a)->withProperties(['keys' => array_keys($data)])->log('storefront_marketing_changed');
        Notification::make()->title(__('orders.saved'))->success()->send();
    }
}
