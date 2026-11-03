<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Content\Models\LandingPage;
use App\Domain\Content\Models\ProductPackage;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductPackageResource extends CommerceResource
{
    protected static ?string $model = ProductPackage::class;

    protected static ?string $slug = 'product-packages';

    protected static string $permission = 'landing.manage';

    public static function getNavigationGroup(): ?string
    {
        return __('appearance.appearance');
    }

    public static function getModelLabel(): string
    {
        return __('landing.packages');
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('landing_page_id')->label(__('landing.landing_pages'))->options(LandingPage::all()->mapWithKeys(fn ($p) => [$p->id => $p->title])->all())->searchable()->required(),
            Select::make('role')->label(__('landing.package_role'))->options(collect(['primary', 'bump', 'upsell', 'downsell'])->mapWithKeys(fn ($r) => [$r => __('landing.'.$r)])->all())->default('primary')->required(),
            Select::make('product_id')->label(__('catalog.products'))->options(Product::query()->limit(500)->get()->mapWithKeys(fn ($p) => [$p->id => $p->name])->all())->searchable()->live()->afterStateUpdated(fn ($set) => $set('variant_id', null))->required(),
            Select::make('variant_id')->label(__('catalog.variant'))->options(fn ($get) => ProductVariant::where('product_id', $get('product_id'))->where('is_active', true)->get()->mapWithKeys(fn ($v) => [$v->id => catalog_variant_label($v)])->all())->searchable(),
            TextInput::make('label.en')->label(__('landing.package_label').' · English')->required()->maxLength(100), TextInput::make('label.bn')->label(__('landing.package_label').' · বাংলা')->maxLength(100), TextInput::make('qty')->label(__('landing.units_in_package'))->integer()->minValue(1)->maxValue(100)->default(1)->required(), TextInput::make('price')->label(__('landing.package_price_minor'))->integer()->minValue(0)->maxValue(100000000000)->required(), Toggle::make('free_shipping')->label(__('checkout.free_shipping')), Toggle::make('is_active')->label(__('catalog.is_active'))->default(true), TextInput::make('sort')->label(__('content.sort'))->integer()->default(0)->minValue(0)->maxValue(100000)->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('label')->label(__('landing.package_label'))->formatStateUsing(fn ($record) => $record->label[app()->getLocale()] ?? $record->label['en']), TextColumn::make('role'), TextColumn::make('qty'), TextColumn::make('price')->formatStateUsing(fn ($state) => catalog_money((int) $state))])->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ProductPackageResource\Pages\ManageRecords::route('/')];
    }
}
