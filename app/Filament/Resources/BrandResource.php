<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Brand;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class BrandResource extends CatalogResource
{
    protected static ?string $model = Brand::class;

    protected static ?string $slug = 'brands';

    public static function form(Form $form): Form
    {
        return $form->schema([...static::translated('name', required: true), TextInput::make('slug')->required()->unique(ignoreRecord: true), ...static::translated('description', true), FileUpload::make('image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])->disk('public')->directory('catalog')->maxSize(4096), TextInput::make('seo.title')->label(__('catalog.seo_title'))->maxLength(255), TextInput::make('seo.description')->label(__('catalog.seo_description'))->maxLength(500), Toggle::make('is_active')->default(true)])->columns(2);
    }

    public static function getPages(): array
    {
        return ['index' => BrandResource\Pages\ManageRecords::route('/')];
    }
}
