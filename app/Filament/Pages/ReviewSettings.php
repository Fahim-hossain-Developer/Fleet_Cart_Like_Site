<?php

namespace App\Filament\Pages;

use App\Domain\Orders\Actions\OrderAccess;
use App\Domain\Settings\Actions\Settings;
use App\Models\Admin;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** @property Form $form */
class ReviewSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.review-settings';

    protected static ?string $navigationIcon = 'heroicon-o-star';

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
        return __('marketing.review_settings');
    }

    public function getTitle(): string
    {
        return __('marketing.review_settings');
    }

    public function mount(): void
    {
        $this->form->fill(['reviews_moderate' => store_setting('reviews_moderate', true), 'reviews_require_verified' => store_setting('reviews_require_verified', true), 'reviews_min_length' => store_setting('reviews_min_length', 20), 'reviews_allow_photos' => store_setting('reviews_allow_photos', true), 'reviews_max_photos' => store_setting('reviews_max_photos', 3), 'reviews_blocked_words' => store_setting('reviews_blocked_words', []), 'reviews_request_enabled' => store_setting('reviews_request_enabled', false), 'reviews_request_days' => store_setting('reviews_request_days', 3)]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([Toggle::make('reviews_moderate')->label(__('marketing.require_moderation')), Toggle::make('reviews_require_verified')->label(__('marketing.require_purchase')), TextInput::make('reviews_min_length')->label(__('marketing.min_text'))->integer()->minValue(10)->maxValue(1000)->required(), Toggle::make('reviews_allow_photos')->label(__('marketing.allow_photos')), TextInput::make('reviews_max_photos')->label(__('marketing.max_photos'))->integer()->minValue(0)->maxValue(5)->required(), TagsInput::make('reviews_blocked_words')->label(__('marketing.blocked_words'))->rules(['array', 'max:100'])->nestedRecursiveRules(['string', 'max:50']), Toggle::make('reviews_request_enabled')->label(__('marketing.enable_requests')), TextInput::make('reviews_request_days')->label(__('marketing.request_days'))->integer()->minValue(1)->maxValue(30)->required()])->statePath('data');
    }

    public function submit(): void
    {
        $a = app(OrderAccess::class)->staff('marketing.manage');
        $data = $this->form->getState();
        foreach ($data as $key => $value) {
            app(Settings::class)->set($key, $value);
        }activity()->causedBy($a)->withProperties(['keys' => array_keys($data)])->log('review_settings_changed');
        Notification::make()->title(__('orders.saved'))->success()->send();
    }
}
