<?php

namespace App\Filament\Resources;

use App\Domain\Content\Models\ContactMessage;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactMessageResource extends CommerceResource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $slug = 'contact-messages';

    protected static string $permission = 'messages.manage';

    public static function getNavigationGroup(): ?string
    {
        return __('content.content');
    }

    public static function getModelLabel(): string
    {
        return __('content.contact_messages');
    }

    public static function getPluralModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('name')->label(__('orders.name'))->disabled(), TextInput::make('email')->label(__('orders.email'))->disabled(), Textarea::make('message')->label(__('content.message'))->disabled()->rows(8), Toggle::make('is_closed')->label(__('content.is_closed'))]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label(__('orders.name')), IconColumn::make('is_closed')->label(__('content.is_closed'))->boolean(), TextColumn::make('created_at')->label(__('content.received_at'))->dateTime()])->defaultSort('id', 'desc')->actions([EditAction::make()->label(__('content.read_message')), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ContactMessageResource\Pages\ManageRecords::route('/')];
    }
}
