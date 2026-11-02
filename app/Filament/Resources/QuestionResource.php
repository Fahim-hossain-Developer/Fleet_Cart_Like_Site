<?php

namespace App\Filament\Resources;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Actions\ManageReviews;
use App\Domain\Marketing\Models\Question;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuestionResource extends MarketingResource
{
    protected static ?string $model = Question::class;

    protected static ?string $slug = 'questions';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('display_name')->label(__('marketing.display_name'))->disabled(), Textarea::make('question')->label(__('marketing.question'))->disabled()->rows(5), Select::make('status')->label(__('content.status'))->options(['pending' => __('marketing.pending'), 'approved' => __('marketing.approved'), 'rejected' => __('marketing.rejected')])->required(), Textarea::make('answer')->label(__('marketing.answer'))->maxLength(3000)->required(fn (Get $get) => $get('status') === 'approved')]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('product_id')->label(__('catalog.products'))->formatStateUsing(fn ($record) => Product::withTrashed()->find($record->product_id)->name ?? '#'.$record->product_id), TextColumn::make('display_name')->label(__('marketing.display_name')), TextColumn::make('question')->label(__('marketing.question'))->limit(60), TextColumn::make('status')->label(__('content.status'))->formatStateUsing(fn ($state) => __('marketing.'.$state))])->defaultSort('id', 'desc')->actions([EditAction::make()->label(__('marketing.answer'))->using(function (Question $record, array $data): Question {
            app(ManageReviews::class)->answer($record, $data['status'], $data['answer'] ?? null);

            return $record->refresh();
        }), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => QuestionResource\Pages\ManageRecords::route('/')];
    }
}
