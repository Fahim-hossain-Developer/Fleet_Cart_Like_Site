<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Tag;
use App\Domain\Content\Actions\SectionCatalog;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Domain\Content\Models\PostCategory;
use App\Domain\Content\Models\Slider;
use App\Domain\Settings\Theme\ThemeConfig;
use App\Models\Admin;
use Filament\Forms\Components\Component;
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
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

abstract class ContentResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return __('content.content');
    }

    public static function getModelLabel(): string
    {
        return __('content.'.static::getSlug());
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function canViewAny(): bool
    {
        $a = auth('admin')->user();

        return $a instanceof Admin && $a->is_active && $a->hasPermissionTo('content.manage', 'admin');
    }

    /** @return array<Component> */
    public static function text(string $key, bool $rich = false, bool $required = false): array
    {
        $class = $rich ? RichEditor::class : TextInput::class;

        return [$class::make($key.'.en')->label(__('content.'.$key).' · English')->required($required)->maxLength($rich ? 100000 : 200), $class::make($key.'.bn')->label(__('content.'.$key).' · বাংলা')->maxLength($rich ? 100000 : 200)];
    }

    protected static function image(string $key): FileUpload
    {
        return FileUpload::make($key)->label(__('content.'.$key))->disk('public')->directory('content')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])->maxSize(6144);
    }

    public static function form(Form $form): Form
    {
        $kind = static::getSlug();
        $fields = [...static::text('title', false, true), ...static::text('body', true), ...static::text('excerpt')];
        if (in_array($kind, ['pages', 'posts', 'post-categories'], true)) {
            $fields[] = TextInput::make('slug')->label(__('catalog.slug'))->required()->maxLength(160)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D')->unique(ignoreRecord: true);
        }
        if (in_array($kind, ['menus', 'sliders'], true)) {
            $fields[] = TextInput::make('key')->label(__('content.key'))->required()->regex('/^[a-z0-9_-]+$/D')->maxLength(32)->unique(ignoreRecord: true);
        }
        if ($kind === 'pages') {
            $fields[] = Select::make('template')->label(__('content.template'))->options(['standard' => __('content.standard'), 'wide' => __('content.wide')])->default('standard')->required();
        }
        if ($kind === 'posts') {
            $fields[] = Select::make('post_category_id')->label(__('content.post-categories'))->options(PostCategory::all()->mapWithKeys(fn ($record) => [$record->id => $record->title])->all())->searchable();
            $fields[] = TagsInput::make('tags')->label(__('catalog.tags'))->nestedRecursiveRules(['string', 'max:50'])->rules(['array', 'max:20']);
        }
        if ($kind === 'menu-items') {
            $fields[] = Select::make('menu_id')->label(__('content.menus'))->options(Menu::all()->mapWithKeys(fn ($record) => [$record->id => $record->title])->all())->required()->live();
            $fields[] = Select::make('parent_id')->label(__('content.parent'))->options(fn (Get $get, $record) => MenuItem::where('menu_id', $get('menu_id'))->when($record, fn ($q) => $q->whereKeyNot($record->id))->pluck('title', 'id'));
            $fields[] = Toggle::make('new_tab')->label(__('content.new_tab'));
        }
        if ($kind === 'slides') {
            $fields[] = Select::make('slider_id')->label(__('content.sliders'))->options(Slider::all()->mapWithKeys(fn ($record) => [$record->id => $record->title])->all())->required();
            $fields = array_merge($fields, static::text('button_label'));
        }
        if (in_array($kind, ['slides', 'banners', 'popups'], true)) {
            $fields[] = static::image('image');
            $fields[] = static::image('mobile_image')->visible($kind !== 'popups');
        }
        if (in_array($kind, ['slides', 'banners', 'popups', 'menu-items'], true)) {
            $fields[] = TextInput::make('url')->label(__('content.url'))->maxLength(2048)->required($kind === 'menu-items')->helperText(__('content.safe_link'));
        }
        if ($kind === 'banners') {
            $fields[] = Select::make('position')->label(__('content.position'))->options(['home' => __('content.home'), 'shop' => __('content.shop'), 'blog' => __('content.blog')])->default('home')->required();
        }
        if ($kind === 'faq-entries') {
            $fields[] = TextInput::make('group')->label(__('content.group'))->default('general')->maxLength(64)->required();
        }
        if ($kind === 'home-sections') {
            $fields[] = Select::make('type')->label(__('content.type'))->options(array_combine(SectionCatalog::TYPES, array_map(fn ($v) => __('content.type_'.$v), SectionCatalog::TYPES)))->required()->live();
            $fields[] = Select::make('locale')->label(__('content.locale'))->options(['en' => 'English', 'bn' => 'বাংলা']);
            $fields[] = Select::make('config.style')->label(__('appearance.section_style'))->options(collect(ThemeConfig::STYLES)->mapWithKeys(fn ($s) => [$s => __('appearance.option_'.$s)])->all());
            $fields[] = Select::make('config.source')->label(__('content.source'))->options(collect(['new', 'sale', 'featured', 'best_seller', 'category', 'tag', 'flash'])->mapWithKeys(fn ($s) => [$s => __('content.source_'.$s)])->all())->default('new');
            $fields[] = Select::make('config.product_ids')->label(__('catalog.products'))->multiple()->searchable()->options(Product::published()->limit(500)->get()->mapWithKeys(fn ($record) => [$record->id => $record->name])->all());
            $fields[] = Select::make('config.category_id')->label(__('catalog.categories'))->options(Category::all()->mapWithKeys(fn ($record) => [$record->id => $record->name])->all());
            $fields[] = Select::make('config.tag_id')->label(__('catalog.tags'))->options(Tag::query()->pluck('name', 'id')->all());
            $fields[] = Select::make('config.slider_id')->label(__('content.sliders'))->options(Slider::all()->mapWithKeys(fn ($record) => [$record->id => $record->title])->all());
            $fields[] = TextInput::make('config.limit')->label(__('content.limit'))->integer()->minValue(1)->maxValue(24)->default(8);
            $fields[] = TextInput::make('config.video_url')->label(__('content.video_url'))->maxLength(2048);
            $fields[] = DateTimePicker::make('config.ends_at')->label(__('content.countdown_end'));
            $fields[] = static::image('config.image')->label(__('content.image'));
            $fields[] = Repeater::make('config.items')->label(__('content.items'))->maxItems(24)->collapsible()->schema([...static::text('title'), Textarea::make('body.en')->label(__('content.body').' · English')->maxLength(2000), Textarea::make('body.bn')->label(__('content.body').' · বাংলা')->maxLength(2000), static::image('image'), TextInput::make('url')->label(__('content.url'))->maxLength(2048), TextInput::make('video_url')->label(__('content.video_url'))->maxLength(2048), Select::make('product_id')->label(__('catalog.products'))->searchable()->options(Product::published()->limit(500)->get()->mapWithKeys(fn ($record) => [$record->id => $record->name])->all()), TextInput::make('price')->label(__('content.price'))->integer()->minValue(0)->maxValue(100000000000), TextInput::make('x')->label(__('content.x'))->integer()->minValue(0)->maxValue(100), TextInput::make('y')->label(__('content.y'))->integer()->minValue(0)->maxValue(100)])->columnSpanFull();
        }
        if ($kind === 'popups') {
            $fields[] = Select::make('trigger')->label(__('content.trigger'))->options(['delay' => __('content.delay'), 'exit' => __('content.exit')])->default('delay')->required();
            $fields[] = TextInput::make('delay_seconds')->label(__('content.delay_seconds'))->integer()->minValue(1)->maxValue(300)->default(15)->required();
            $fields[] = TextInput::make('frequency_hours')->label(__('content.frequency_hours'))->integer()->minValue(1)->maxValue(8760)->default(168)->required();
            $fields[] = TagsInput::make('paths')->label(__('content.paths'))->rules(['array', 'max:30']);
        }
        if (in_array($kind, ['pages', 'posts'], true)) {
            $fields[] = Section::make(__('content.seo'))->schema([TextInput::make('seo.en.title')->label(__('content.meta_title').' · English')->maxLength(200), TextInput::make('seo.bn.title')->label(__('content.meta_title').' · বাংলা')->maxLength(200), Textarea::make('seo.en.description')->label(__('content.meta_description').' · English')->maxLength(500), Textarea::make('seo.bn.description')->label(__('content.meta_description').' · বাংলা')->maxLength(500), TextInput::make('seo.canonical')->label(__('content.canonical'))->maxLength(2048), static::image('seo.image')->label(__('content.image'))]);
        }

        return $form->schema([...$fields, Section::make(__('content.publication'))->schema([Select::make('status')->label(__('content.status'))->options(['draft' => __('content.draft'), 'published' => __('content.published')])->default('draft')->required(), Toggle::make('is_active')->label(__('catalog.is_active'))->default(true), TextInput::make('sort')->label(__('content.sort'))->integer()->minValue(0)->maxValue(100000)->default(0)->required(), DateTimePicker::make('published_at')->label(__('content.published_at')), DateTimePicker::make('starts_at')->label(__('content.starts_at')), DateTimePicker::make('ends_at')->label(__('content.ends_at'))->after('starts_at')])])->columns(2);
    }

    public static function table(Table $table): Table
    {
        $actions = [EditAction::make()->modalWidth('7xl'), DeleteAction::make()];
        if (in_array(static::getSlug(), ['pages', 'posts'], true)) {
            $actions[] = Action::make('preview')->label(__('content.preview'))->icon('heroicon-o-eye')->url(fn ($record) => URL::temporarySignedRoute('content.preview', now()->addMinutes(10), ['kind' => static::getSlug(), 'id' => $record->id]))->openUrlInNewTab();
        }

        return $table->columns([TextColumn::make('title')->label(__('content.title'))->searchable()->limit(45), TextColumn::make('status')->label(__('content.status'))->formatStateUsing(fn ($state) => __('content.'.$state)), IconColumn::make('is_active')->label(__('catalog.is_active'))->boolean(), TextColumn::make('published_at')->label(__('content.published_at'))->dateTime(), TextColumn::make('sort')->label(__('content.sort'))])->defaultSort('sort')->reorderable('sort')->actions($actions)->bulkActions([DeleteBulkAction::make()])->filters([SelectFilter::make('status')->label(__('content.status'))->options(['draft' => __('content.draft'), 'published' => __('content.published')])]);
    }
}
