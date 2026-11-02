<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Attribute;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class AttributeResource extends CatalogResource
{
    protected static ?string $model = Attribute::class;

    protected static ?string $slug = 'attributes';

    public static function form(Form $form): Form
    {
        return $form->schema([...static::translated('name', required: true), TextInput::make('slug')->required()->unique(ignoreRecord: true), Select::make('type')->options(['text' => __('catalog.text'), 'color' => __('catalog.color'), 'image' => __('catalog.image')])->default('text')->required(), Toggle::make('is_filterable')->default(true), Repeater::make('values')->defaultItems(0)->label(__('catalog.values'))->relationship()->schema([...static::translated('name', required: true), TextInput::make('value')->required(), FileUpload::make('image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])->disk('public')->directory('catalog')->maxSize(4096), TextInput::make('sort')->numeric()->default(0)])->orderColumn('sort')])->columns(2);
    }

    public static function getPages(): array
    {
        return ['index' => AttributeResource\Pages\ManageRecords::route('/')];
    }
}
