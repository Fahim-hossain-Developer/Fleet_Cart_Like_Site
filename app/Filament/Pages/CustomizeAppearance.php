<?php

namespace App\Filament\Pages;

use App\Domain\Settings\Models\ThemePreset;
use App\Domain\Settings\Theme\Theme;
use App\Domain\Settings\Theme\ThemeConfig;
use App\Models\Admin;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;

/** @property Form $form */
class CustomizeAppearance extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.customize-appearance';

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    /** @var array<string,mixed>|null */
    public ?array $data = [];

    #[Locked]
    public int $revision = 0;

    public string $presetName = '';

    public ?int $presetId = null;

    #[Locked]
    public string $preview = '';

    public static function canAccess(): bool
    {
        $a = auth('admin')->user();

        return $a instanceof Admin && $a->is_active && $a->hasPermissionTo('theme.manage', 'admin');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('appearance.appearance');
    }

    public static function getNavigationLabel(): string
    {
        return __('appearance.customize');
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function mount(): void
    {
        $state = app(Theme::class)->state();
        $this->revision = $state['revision'];
        $this->form->fill($state['config']);
    }

    public function form(Form $form): Form
    {
        $fields = [FileUpload::make('logo')->label(__('appearance.logo'))->disk('public')->directory('branding')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])->maxSize(4096), TextInput::make('logo_width')->label(__('appearance.logo_width'))->integer()->minValue(80)->maxValue(300)->required()];
        foreach (['primary', 'secondary', 'accent', 'background'] as $key) {
            $fields[] = ColorPicker::make($key)->label(__('appearance.'.$key))->required();
        }
        foreach (['header' => ThemeConfig::HEADERS, 'footer' => ThemeConfig::FOOTERS, 'card' => ThemeConfig::CARDS, 'product' => ThemeConfig::PRODUCTS, 'listing' => ThemeConfig::LISTINGS, 'font' => ThemeConfig::FONTS, 'section_style' => ThemeConfig::STYLES, 'shadow' => ['none', 'soft', 'lifted']] as $key => $values) {
            $fields[] = Select::make($key)->label(__('appearance.'.$key))->options(array_combine($values, array_map(fn ($v) => __('appearance.option_'.$v), $values)))->required();
        }
        $fields[] = TextInput::make('radius')->label(__('appearance.radius'))->integer()->minValue(0)->maxValue(32)->required();

        return $form->schema($fields)->columns(2)->statePath('data');
    }

    public function previewTheme(): void
    {
        $draft = app(Theme::class)->draft($this->form->getState(), $this->revision);
        $this->preview = app(Theme::class)->previewUrl($draft);
    }

    public function publish(): void
    {
        $this->revision = app(Theme::class)->publish($this->form->getState(), $this->revision);
        $this->preview = '';
        Notification::make()->title(__('appearance.published'))->success()->send();
    }

    public function loadPreset(): void
    {
        app(Theme::class)->staff();
        $preset = ThemePreset::findOrFail($this->presetId);
        $this->form->fill(array_replace(app(ThemeConfig::class)->defaults(), $preset->config));
        $this->previewTheme();
    }

    public function savePreset(): void
    {
        app(Theme::class)->savePreset($this->form->getState(), $this->presetName);
        $this->presetName = '';
        Notification::make()->title(__('orders.saved'))->success()->send();
    }
}
