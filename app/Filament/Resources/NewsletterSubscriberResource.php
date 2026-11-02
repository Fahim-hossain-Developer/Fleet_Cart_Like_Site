<?php

namespace App\Filament\Resources;

use App\Domain\Marketing\Actions\Newsletter;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NewsletterSubscriberResource extends MarketingResource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $slug = 'newsletter-subscribers';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('masked_email')->label(__('orders.email'))->state(fn ($record) => app(Newsletter::class)->masked($record)), TextColumn::make('status')->label(__('content.status')), TextColumn::make('source')->label(__('content.source')), TextColumn::make('confirmed_at')->label(__('marketing.confirmed_at'))->dateTime(), TextColumn::make('unsubscribed_at')->label(__('marketing.unsubscribed_at'))->dateTime()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => NewsletterSubscriberResource\Pages\ManageRecords::route('/')];
    }
}
