<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Actions\BulkCatalogUpdate;
use App\Domain\Catalog\Actions\DuplicateProduct;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ProductResource extends CatalogResource
{
    protected static ?string $model = Product::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('catalog.details'))->schema([...static::translated('name', required: true), TextInput::make('sku')->required()->maxLength(220)->unique(ignoreRecord: true), TextInput::make('slug')->required()->maxLength(220)->unique(ignoreRecord: true), Select::make('type')->options(['simple' => __('catalog.simple'), 'variable' => __('catalog.variable'), 'digital' => __('catalog.digital')])->required()->default('simple'), Select::make('status')->options(['draft' => __('catalog.draft'), 'active' => __('catalog.active')])->required()->default('draft'), Select::make('visibility')->options(['visible' => __('catalog.visible'), 'hidden' => __('catalog.hidden')])->default('visible')->required(), ...static::translated('short_desc'), ...static::translated('description', true)])->columns(2),
            Section::make(__('catalog.pricing'))->schema([TextInput::make('price')->label(__('catalog.minor_price'))->required()->integer()->minValue(0)->maxValue(100000000000)->default(0), TextInput::make('special_price')->integer()->minValue(0)->lte('price'), TextInput::make('cost')->integer()->minValue(0), DateTimePicker::make('special_from'), DateTimePicker::make('special_to')->afterOrEqual('special_from'), Toggle::make('manage_stock')->default(true), TextInput::make('qty')->integer()->minValue(0)->default(0), Select::make('stock_status')->options(['in_stock' => __('catalog.in_stock'), 'out_of_stock' => __('catalog.out_of_stock'), 'backorder' => __('catalog.backorder')])->default('in_stock'), TextInput::make('low_stock_at')->integer()->minValue(0)->default(5), TextInput::make('max_per_order')->integer()->minValue(1), Toggle::make('is_featured'), DateTimePicker::make('new_until'), Toggle::make('free_shipping'), Toggle::make('is_preorder')])->columns(3),
            Section::make(__('catalog.taxonomy'))->schema([Select::make('brand_id')->relationship('brand', 'name')->getOptionLabelFromRecordUsing(fn ($record) => $record->name)->searchable()->preload(), Select::make('categories')->relationship('categories', 'name')->getOptionLabelFromRecordUsing(fn ($record) => $record->name)->multiple()->preload(), Select::make('tags')->relationship('tags', 'name')->getOptionLabelFromRecordUsing(fn ($record) => $record->name)->multiple()->preload(), Select::make('attribute_set_id')->relationship('attributeSet', 'name')->getOptionLabelFromRecordUsing(fn ($record) => $record->name)->preload(), Select::make('attributeValues')->relationship('attributeValues', 'name')->getOptionLabelFromRecordUsing(fn ($record) => $record->attribute->name.': '.$record->name)->multiple()->preload()])->columns(2),
            Section::make(__('catalog.variants'))->schema([Repeater::make('variants')->defaultItems(0)->relationship()->schema([TextInput::make('sku')->required()->unique(ignoreRecord: true), KeyValue::make('attributes')->required(), TextInput::make('price')->integer()->minValue(0)->required(), TextInput::make('special_price')->integer()->minValue(0)->lte('price'), TextInput::make('qty')->integer()->minValue(0)->default(0), FileUpload::make('image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])->disk('public')->directory('catalog')->maxSize(4096), Toggle::make('is_active')->default(true)])->columns(3)]),
            Section::make(__('catalog.options'))->schema([Repeater::make('options')->defaultItems(0)->relationship()->orderColumn('sort')->schema([...static::translated('label', required: true), Select::make('type')->options(array_combine(['text', 'textarea', 'dropdown', 'checkbox', 'radio', 'date', 'file'], array_map(fn ($key) => __('catalog.'.$key), ['text', 'textarea', 'dropdown', 'checkbox', 'radio', 'date', 'file'])))->required(), Toggle::make('required'), TextInput::make('price')->integer()->minValue(0)->default(0), Select::make('price_type')->options(['fixed' => __('catalog.fixed'), 'percent' => __('catalog.percent')])->default('fixed'), Repeater::make('values')->defaultItems(0)->relationship()->orderColumn('sort')->schema([...static::translated('label', required: true), TextInput::make('price')->integer()->minValue(0)->default(0)])])]),
            Section::make(__('catalog.media'))->schema([FileUpload::make('new_images')->label(__('catalog.upload_images'))->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])->multiple()->reorderable()->storeFiles(false)->maxSize(4096)->maxFiles(12)->dehydrated(false), TextInput::make('video_url')->url()->maxLength(255), FileUpload::make('video_file')->disk('public')->directory('product-videos')->acceptedFileTypes(['video/mp4', 'video/webm'])->maxSize(20480), Repeater::make('gallery_metadata')->defaultItems(0)->reorderable()->schema([TextInput::make('id')->disabled(), TextInput::make('alt')->maxLength(255), Toggle::make('remove')])->dehydrated(false)]),
            Section::make(__('catalog.downloads'))->schema([Repeater::make('downloads')->defaultItems(0)->relationship()->schema([FileUpload::make('file')->disk('local')->directory('product-downloads')->acceptedFileTypes(['application/pdf', 'application/zip', 'text/plain'])->maxSize(20480), TextInput::make('external_url')->url(), TextInput::make('limit')->integer()->minValue(1), TextInput::make('expiry_days')->integer()->minValue(1)])]),
            Section::make(__('catalog.related'))->schema([Repeater::make('relations')->defaultItems(0)->relationship()->schema([Select::make('related_id')->options(fn () => Product::query()->get()->mapWithKeys(fn ($p) => [$p->id => $p->name])->all())->searchable()->required(), Select::make('type')->options(['related' => __('catalog.related'), 'upsell' => __('catalog.upsell'), 'crosssell' => __('catalog.crosssell')])->default('related')->required()])]),
            Section::make(__('catalog.tabs'))->schema([Select::make('hiddenTabs')->label(__('catalog.hidden_tabs'))->relationship('hiddenTabs', 'title', modifyQueryUsing: fn ($query) => $query->where('scope', 'global'))->getOptionLabelFromRecordUsing(fn ($record) => $record->title)->multiple()->preload(), Repeater::make('tabs')->defaultItems(0)->relationship()->orderColumn('sort')->schema([...static::translated('title', required: true), ...static::translated('body', true, true), Toggle::make('is_active')->default(true)])]),
            Section::make('SEO')->schema([TextInput::make('seo.title')->label(__('catalog.seo_title'))->maxLength(255), TextInput::make('seo.description')->label(__('catalog.seo_description'))->maxLength(500), TextInput::make('seo.canonical')->url(), FileUpload::make('seo.og_image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])->disk('public')->directory('catalog')->maxSize(4096)])->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label(__('catalog.name'))->formatStateUsing(fn (Product $record) => $record->name)->searchable(), TextColumn::make('sku')->label('SKU')->searchable(), TextColumn::make('status')->label(__('catalog.status'))->badge(), TextColumn::make('price')->label(__('catalog.minor_price'))->sortable(), TextColumn::make('qty')->label(__('catalog.qty'))->sortable()])
            ->actions([EditAction::make(), Action::make('duplicate')->label(__('catalog.duplicate'))->form([Toggle::make('keep_stock')->label(__('catalog.keep_stock'))->default(fn () => (bool) store_setting('catalog_clone_keep_stock', false))])->action(fn (Product $record, array $data) => app(DuplicateProduct::class)->execute($record, $data['keep_stock']))])
            ->bulkActions([BulkAction::make('update_catalog')->label(__('catalog.bulk_update'))->form([TextInput::make('price')->label(__('catalog.minor_price'))->integer()->minValue(0)->maxValue(100000000000), TextInput::make('qty')->label(__('catalog.qty'))->integer()->minValue(0)->maxValue(1000000), Select::make('categories')->label(__('catalog.categories'))->options(fn () => Category::all()->mapWithKeys(fn ($category) => [$category->id => $category->name])->all())->multiple()])->action(function (Collection $records, array $data): void {
                $products = [];
                foreach ($records as $record) {
                    assert($record instanceof Product);
                    $products[] = $record;
                } app(BulkCatalogUpdate::class)->execute($products, $data);
            }), DeleteBulkAction::make(), BulkAction::make('duplicate')->label(__('catalog.duplicate_selected'))->action(function (Collection $records): void {
                foreach ($records as $record) {
                    assert($record instanceof Product);
                    app(DuplicateProduct::class)->execute($record);
                }
            }), BulkAction::make('publish')->label(__('catalog.publish'))->action(function (Collection $records): void {
                foreach ($records as $record) {
                    assert($record instanceof Product);
                    $record->update(['status' => 'active']);
                }
            }), BulkAction::make('unpublish')->label(__('catalog.unpublish'))->action(function (Collection $records): void {
                foreach ($records as $record) {
                    assert($record instanceof Product);
                    $record->update(['status' => 'draft']);
                }
            })]);
    }

    public static function getPages(): array
    {
        return ['index' => ProductResource\Pages\ListProducts::route('/'), 'create' => ProductResource\Pages\CreateProduct::route('/create'), 'edit' => ProductResource\Pages\EditProduct::route('/{record}/edit')];
    }
}
