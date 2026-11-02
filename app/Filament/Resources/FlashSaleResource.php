<?php

namespace App\Filament\Resources;

use App\Domain\Marketing\Models\FlashSale;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class FlashSaleResource extends MarketingResource
{
    protected static ?string $model = FlashSale::class;

    protected static ?string $slug = 'flash-sales';

    public static function form(Form $form): Form
    {
        return $form->schema([...static::names(), Toggle::make('is_active')->label(__('catalog.is_active'))->default(false), DateTimePicker::make('starts_at')->label(__('content.starts_at'))->required(), DateTimePicker::make('ends_at')->label(__('content.ends_at'))->after('starts_at')->required()]);
    }

    public static function getPages(): array
    {
        return ['index' => FlashSaleResource\Pages\ManageRecords::route('/')];
    }
}
