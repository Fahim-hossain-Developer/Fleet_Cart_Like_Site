<?php

namespace App\Filament\Resources;

use App\Domain\Marketing\Models\TrackingEvent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TrackingEventResource extends MarketingResource
{
    protected static ?string $model = TrackingEvent::class;

    protected static ?string $slug = 'tracking-events';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('event_id'), TextColumn::make('name'), TextColumn::make('status'), TextColumn::make('attempts'), TextColumn::make('failure_code'), TextColumn::make('accepted_at')->dateTime()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => TrackingEventResource\Pages\ManageRecords::route('/')];
    }
}
