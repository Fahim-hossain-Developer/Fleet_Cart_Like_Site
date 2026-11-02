<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductTab;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductTabResource extends CatalogResource
{
    protected static ?string $model = ProductTab::class;

    protected static ?string $slug = 'product-tabs';

    public static function form(Form $form): Form
    {
        return $form->schema([Select::make('scope')->options(['global' => __('catalog.global'), 'product' => __('catalog.product')])->required()->default('global'), Select::make('product_id')->options(fn () => Product::query()->pluck('name', 'id')->all())->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)->searchable(), Select::make('category_ids')->options(fn () => Category::all()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all())->multiple(), ...static::translated('title', required: true), ...static::translated('body', true, true), TextInput::make('sort')->numeric()->default(0), Toggle::make('is_active')->default(true)])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->reorderable('sort')->defaultSort('sort')->columns([TextColumn::make('title')->label(__('catalog.title')), TextColumn::make('scope')->label(__('catalog.scope'))])->actions([EditAction::make()])->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ProductTabResource\Pages\ManageRecords::route('/')];
    }
}
