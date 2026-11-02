<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class CategoryResource extends CatalogResource
{
    protected static ?string $model = Category::class;

    protected static ?string $slug = 'categories';

    public static function form(Form $form): Form
    {
        return $form->schema([...static::translated('name', required: true), TextInput::make('slug')->required()->maxLength(220)->regex('/^[a-z0-9-]+$/')->unique(ignoreRecord: true), Select::make('parent_id')->label(__('catalog.parent'))->options(fn () => Category::query()->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all())->getOptionLabelUsing(fn ($value) => Category::find($value)?->name)->disableOptionWhen(fn ($value, $record) => $record && (int) $value === $record->id), ...static::translated('description', true), FileUpload::make('image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])->disk('public')->directory('catalog')->maxSize(4096), FileUpload::make('banner')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif'])->disk('public')->directory('catalog')->maxSize(4096), TextInput::make('sort')->numeric()->minValue(0)->default(0), TextInput::make('seo.title')->label(__('catalog.seo_title'))->maxLength(255), TextInput::make('seo.description')->label(__('catalog.seo_description'))->maxLength(500), Toggle::make('is_active')->default(true), Toggle::make('show_in_menu')->default(true)])->columns(2);
    }

    public static function getPages(): array
    {
        return ['index' => CategoryResource\Pages\ManageRecords::route('/')];
    }
}
