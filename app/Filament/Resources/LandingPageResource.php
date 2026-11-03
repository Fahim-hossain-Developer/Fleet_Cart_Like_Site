<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Actions\SectionCatalog;
use App\Domain\Content\Landing\LandingRules;
use App\Domain\Content\Landing\LandingTemplates;
use App\Domain\Content\Models\LandingPage;
use App\Domain\Content\Models\LandingTemplate;
use App\Domain\Content\Models\ProductPackage;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LandingPageResource extends CommerceResource
{
    protected static ?string $model = LandingPage::class;

    protected static ?string $slug = 'landing-pages';

    protected static string $permission = 'landing.manage';

    public static function getNavigationGroup(): ?string
    {
        return __('appearance.appearance');
    }

    public static function getModelLabel(): string
    {
        return __('landing.landing_pages');
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function form(Form $form): Form
    {
        $translated = fn ($name, $label, $long = false) => [$long ? Textarea::make($name.'.en')->label($label.' · English')->maxLength(3000) : TextInput::make($name.'.en')->label($label.' · English')->maxLength(200), $long ? Textarea::make($name.'.bn')->label($label.' · বাংলা')->maxLength(3000) : TextInput::make($name.'.bn')->label($label.' · বাংলা')->maxLength(200)];

        return $form->schema([Select::make('starter_template')->label(__('landing.start_template'))->options(fn () => LandingTemplate::all()->mapWithKeys(fn ($t) => [$t->id => $t->name[app()->getLocale()] ?? $t->name['en']])->all())->live()->dehydrated(false)->afterStateUpdated(function ($state, $set) {
            $t = LandingTemplate::find($state);
            if ($t) {
                $set('sections', $t->sections);
                $set('form_config', $t->form_config);
            }
        }),
            ...$translated('title', __('content.title')), TextInput::make('slug')->label(__('catalog.slug'))->required()->maxLength(160)->unique(ignoreRecord: true), Select::make('product_id')->label(__('catalog.products'))->options(Product::query()->limit(500)->get()->mapWithKeys(fn ($p) => [$p->id => $p->name])->all())->searchable()->required(),
            Section::make(__('landing.page_sections'))->schema([Repeater::make('sections')->default(fn () => app(LandingTemplates::class)->starters()[0]['sections'])->label(__('landing.page_sections'))->minItems(1)->maxItems(40)->collapsible()->reorderable()->schema([TextInput::make('key')->label(__('landing.section_key'))->required()->default(fn () => 'block-'.strtolower(Str::random(8))), Select::make('type')->label(__('content.type'))->options(collect(array_unique(array_merge(LandingRules::TYPES, SectionCatalog::TYPES)))->mapWithKeys(fn ($type) => [$type => __('landing.section_'.$type)])->all())->required(), ...$translated('title', __('content.title')), RichEditor::make('body.en')->label(__('content.body').' · English')->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3', 'undo', 'redo']), RichEditor::make('body.bn')->label(__('content.body').' · বাংলা')->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'h2', 'h3', 'undo', 'redo']), FileUpload::make('config.image')->label(__('content.image'))->disk('public')->directory('content')->image()->maxSize(4096), TextInput::make('config.video_url')->label(__('content.video_url'))->maxLength(2048), TextInput::make('config.url')->label(__('content.url'))->maxLength(2048), DateTimePicker::make('config.ends_at')->label(__('content.ends_at')), Repeater::make('config.items')->label(__('content.items'))->maxItems(20)->schema([...$translated('title', __('content.title')), ...$translated('body', __('content.body'), true), FileUpload::make('image')->label(__('content.image'))->disk('public')->directory('content')->image()->maxSize(4096), TextInput::make('url')->label(__('content.url'))->maxLength(2048)])])->columnSpanFull()]),
            Section::make(__('landing.order_form'))->schema([Select::make('form_config.mode')->label(__('landing.form_mode'))->options(['inline' => __('landing.inline'), 'popup' => __('landing.popup'), 'sticky' => __('landing.sticky')])->default('inline')->required(), Toggle::make('form_config.email')->label(__('checkout.email'))->default(true), Toggle::make('form_config.alternate_phone')->label(__('landing.alternate_phone')), Toggle::make('form_config.delivery_note')->label(__('landing.delivery_note'))->default(true), Toggle::make('form_config.preferred_time')->label(__('landing.preferred_time')), Toggle::make('form_config.phone_otp')->label(__('landing.require_phone_otp')), ...$translated('form_config.custom_label', __('landing.custom_label')), Select::make('form_config.custom_type')->label(__('landing.custom_type'))->options(['text' => __('landing.text'), 'dropdown' => __('landing.dropdown')])->default('text'), TagsInput::make('form_config.custom_choices')->label(__('landing.custom_choices'))->rules(['array', 'max:20'])->nestedRecursiveRules(['string', 'max:100']), ...array_map(fn ($role) => Select::make('form_config.'.$role.'_package_id')->label(__('landing.'.$role))->options(fn ($record) => $record ? ProductPackage::where('landing_page_id', $record->id)->where('role', $role)->get()->mapWithKeys(fn ($p) => [$p->id => $p->label[app()->getLocale()] ?? $p->label['en']])->all() : []), ['bump', 'upsell', 'downsell']), TextInput::make('form_config.whatsapp')->label('WhatsApp')->maxLength(16)])->columns(2),
            Select::make('status')->label(__('content.status'))->options(['draft' => __('content.draft'), 'published' => __('content.published')])->default('draft')->required(), Toggle::make('is_active')->label(__('catalog.is_active'))->default(true), TextInput::make('sort')->label(__('content.sort'))->integer()->default(0)->required(), DateTimePicker::make('published_at')->label(__('content.published_at')), DateTimePicker::make('starts_at')->label(__('content.starts_at')), DateTimePicker::make('ends_at')->label(__('content.ends_at'))->after('starts_at'),
            Section::make(__('content.seo'))->schema([TextInput::make('seo.en.title')->label(__('appearance.title_en'))->maxLength(200), TextInput::make('seo.bn.title')->label(__('appearance.title_bn'))->maxLength(200), Textarea::make('seo.en.description')->label(__('appearance.description_en'))->maxLength(500), Textarea::make('seo.bn.description')->label(__('appearance.description_bn'))->maxLength(500)]),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->label(__('content.title'))->formatStateUsing(fn ($record) => $record->title), TextColumn::make('slug'), TextColumn::make('status'), TextColumn::make('orders_count')])->actions([Action::make('template')->label(__('landing.start_template'))->requiresConfirmation()->form([Select::make('template_id')->label(__('landing.template'))->options(LandingTemplate::all()->mapWithKeys(fn ($t) => [$t->id => $t->name[app()->getLocale()] ?? $t->name['en']])->all())->required()])->action(fn ($record, $data) => app(LandingTemplates::class)->apply($record, LandingTemplate::findOrFail($data['template_id']))), Action::make('save_template')->label(__('landing.save_template'))->form([TextInput::make('name')->label(__('marketing.name'))->required()->maxLength(100)])->action(fn ($record, $data) => app(LandingTemplates::class)->save($record, $data['name'])), EditAction::make(), DeleteAction::make(), Action::make('open')->label(__('landing.open_page'))->url(fn ($record) => route('landing.show', $record->slug))->openUrlInNewTab()]);
    }

    public static function getPages(): array
    {
        return ['index' => LandingPageResource\Pages\ManageRecords::route('/')];
    }
}
