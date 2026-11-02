<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Actions\ManageReviews;
use App\Domain\Marketing\Models\Review;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReviewResource extends MarketingResource
{
    protected static ?string $model = Review::class;

    protected static ?string $slug = 'reviews';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('display_name')->label(__('marketing.display_name'))->disabled(), TextInput::make('rating')->label(__('marketing.rating'))->disabled(), TextInput::make('title')->label(__('content.title'))->disabled(), Textarea::make('body')->label(__('marketing.review_text'))->disabled()->rows(6), ViewField::make('images')->label(__('marketing.photos'))->view('filament.forms.review-photos'), Select::make('status')->label(__('content.status'))->options(['pending' => __('marketing.pending'), 'approved' => __('marketing.approved'), 'rejected' => __('marketing.rejected')])->required(), Textarea::make('admin_reply')->label(__('marketing.staff_reply'))->maxLength(3000)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('product_id')->label(__('catalog.products'))->formatStateUsing(fn ($record) => Product::withTrashed()->find($record->product_id)->name ?? '#'.$record->product_id), TextColumn::make('display_name')->label(__('marketing.display_name')), TextColumn::make('rating')->label(__('marketing.rating')), TextColumn::make('status')->label(__('content.status'))->formatStateUsing(fn ($state) => __('marketing.'.$state))])->defaultSort('id', 'desc')->filters([SelectFilter::make('status')->options(['pending' => __('marketing.pending'), 'approved' => __('marketing.approved'), 'rejected' => __('marketing.rejected')])])->actions([EditAction::make()->label(__('marketing.moderate'))->using(function (Review $record, array $data): Review {
            app(ManageReviews::class)->moderate($record, $data['status'], $data['admin_reply'] ?? null);

            return $record->refresh();
        }), DeleteAction::make()->using(function (Review $record): bool {
            app(ManageReviews::class)->delete($record);

            return true;
        })]);
    }

    public static function getPages(): array
    {
        return ['index' => ReviewResource\Pages\ManageRecords::route('/')];
    }
}
