<?php

namespace App\Filament\Resources;

use App\Domain\Marketing\Models\CartRecoveryContact;
use App\Domain\Orders\Actions\OrderAccess;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CartRecoveryContactResource extends MarketingResource
{
    protected static ?string $model = CartRecoveryContact::class;

    protected static ?string $slug = 'cart-recovery-contacts';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id'), TextColumn::make('masked_email')->label(__('orders.email'))->state(fn ($record) => $record->email ? mb_substr($record->email, 0, 1).'***' : '—'), TextColumn::make('last_activity_at')->dateTime(), TextColumn::make('email_verified_at')->dateTime(), TextColumn::make('phone_verified_at')->dateTime(), TextColumn::make('stopped_at')->dateTime(), TextColumn::make('converted_at')->dateTime()])->actions([Action::make('stop')->label(__('marketing.recovery_stop'))->requiresConfirmation()->action(function ($record): void {
            app(OrderAccess::class)->staff('marketing.manage');
            $record->update(['stopped_at' => now(), 'confirmation_hash' => null, 'sms_code_hash' => null]);
            activity()->causedBy(auth('admin')->user())->performedOn($record)->log('cart_recovery_stopped');
        })])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => CartRecoveryContactResource\Pages\ManageRecords::route('/')];
    }
}
