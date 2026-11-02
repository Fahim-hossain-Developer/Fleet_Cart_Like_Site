<?php

namespace App\Filament\Resources;

use App\Domain\Marketing\Models\CartRecoveryMessage;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CartRecoveryMessageResource extends MarketingResource
{
    protected static ?string $model = CartRecoveryMessage::class;

    protected static ?string $slug = 'cart-recovery-messages';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id'), TextColumn::make('cart_recovery_contact_id'), TextColumn::make('channel'), TextColumn::make('kind'), TextColumn::make('stage'), TextColumn::make('status'), TextColumn::make('failure_code'), TextColumn::make('sent_at')->dateTime()])->actions([Action::make('draft')->label(__('marketing.open_whatsapp_draft'))->visible(fn ($record) => $record->channel === 'whatsapp' && $record->status === 'ready_manual')->url(fn ($record) => route('recovery.draft', $record->id))->openUrlInNewTab()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => CartRecoveryMessageResource\Pages\ManageRecords::route('/')];
    }
}
