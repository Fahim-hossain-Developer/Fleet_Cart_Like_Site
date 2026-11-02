<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'customers';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getModelLabel(): string
    {
        return __('orders.customer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('orders.customers');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('name')->label(__('orders.name'))->disabled(), TextInput::make('email')->label(__('orders.email'))->disabled(), TextInput::make('phone')->label(__('orders.phone'))->disabled(), Toggle::make('is_blocked')->label(__('orders.is_blocked')), TagsInput::make('tags')->label(__('orders.tags'))->nestedRecursiveRules(['string', 'max:50'])->rules(['array', 'max:20']), Textarea::make('staff_notes')->label(__('orders.staff_notes'))->maxLength(5000)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label(__('orders.name'))->searchable(), TextColumn::make('email')->label(__('orders.email'))->searchable(), TextColumn::make('phone')->label(__('orders.phone'))->searchable(), TextColumn::make('tags')->label(__('orders.tags'))->badge(), TextColumn::make('last_login_at')->label(__('orders.last_login'))->dateTime()])->filters([TernaryFilter::make('is_blocked')->label(__('orders.is_blocked'))])->actions([ViewAction::make(), EditAction::make(), Action::make('history')->visible(fn () => auth('admin')->user()->can('orders.manage'))->label(__('orders.order_history'))->url(fn (User $record) => OrderResource::getUrl('index', ['tableFilters' => ['user_id' => ['value' => $record->id]]]))]);
    }

    public static function getPages(): array
    {
        return ['index' => CustomerResource\Pages\ManageCustomers::route('/'), 'view' => CustomerResource\Pages\ViewCustomer::route('/{record}')];
    }
}
