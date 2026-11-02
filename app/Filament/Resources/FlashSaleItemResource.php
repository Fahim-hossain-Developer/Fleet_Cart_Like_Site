<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Marketing\Models\FlashSale;
use App\Domain\Marketing\Models\FlashSaleItem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FlashSaleItemResource extends MarketingResource
{
    protected static ?string $model = FlashSaleItem::class;

    protected static ?string $slug = 'flash-sale-items';

    public static function form(Form $form): Form
    {
        return $form->schema([Select::make('flash_sale_id')->label(__('marketing.flash-sales'))->options(FlashSale::all()->mapWithKeys(fn ($r) => [$r->id => $r->name])->all())->required(), Select::make('product_id')->label(__('catalog.products'))->options(Product::published()->limit(500)->get()->mapWithKeys(fn ($r) => [$r->id => $r->name])->all())->required()->live()->disabled(fn ($record) => $record !== null), Select::make('variant_id')->label(__('catalog.variant'))->options(fn (Get $get) => ProductVariant::where('product_id', $get('product_id'))->get()->mapWithKeys(fn ($v) => [$v->id => catalog_variant_label($v)])->all())->disabled(fn ($record) => $record !== null), TextInput::make('price')->label(__('marketing.price'))->integer()->minValue(0)->maxValue(100000000000)->required(), TextInput::make('quota')->label(__('marketing.quota'))->integer()->minValue(1)->maxValue(1000000)->required(), Toggle::make('is_active')->label(__('catalog.is_active'))->default(true)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id'), TextColumn::make('product_id')->label(__('catalog.products'))->formatStateUsing(fn ($record) => Product::find($record->product_id)->name ?? '#'.$record->product_id), TextColumn::make('price')->label(__('marketing.price')), TextColumn::make('quota')->label(__('marketing.quota')), TextColumn::make('sold')->label(__('marketing.allocated'))])->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => FlashSaleItemResource\Pages\ManageRecords::route('/')];
    }
}
