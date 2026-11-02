<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Orders\Actions\CreateManualOrder;
use App\Domain\Shipping\Models\Location;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Filament\Resources\OrderResource;
use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Str;

/** @property Form $form */
class CreateOrder extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = OrderResource::class;

    protected static string $view = 'filament.orders.create';

    /** @var array<string,mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['idempotency_key' => (string) Str::uuid(), 'country' => 'BD', 'payment_method' => 'cod']);
    }

    public function form(Form $form): Form
    {
        $fields = [Select::make('user_id')->label(__('orders.customer'))->searchable()->options(fn () => User::where('is_blocked', false)->limit(300)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name.' · '.($u->phone ?? $u->email)])->all()), TextInput::make('name')->label(__('orders.name'))->required()->maxLength(100), TextInput::make('phone')->label(__('orders.phone'))->required()->maxLength(32), TextInput::make('email')->label(__('orders.email'))->email()->maxLength(254), Hidden::make('country')->default('BD'), Hidden::make('payment_method')->default('cod'), Hidden::make('idempotency_key')];
        foreach (['division', 'district', 'upazila', 'area'] as $type) {
            $fields[] = Select::make($type.'_id')->label(__('checkout.'.$type))->options(fn () => Location::where('type', $type)->get()->mapWithKeys(fn ($record) => [$record->id => ($record->name[app()->getLocale()] ?? $record->name['en'])])->all())->searchable()->required($type !== 'area');
        }

        return $form->schema([...$fields, TextInput::make('address')->label(__('orders.line1'))->required()->maxLength(500), Select::make('shipping_method_id')->label(__('checkout.shipping_method'))->options(fn () => ShippingMethod::where('is_active', true)->get()->mapWithKeys(fn ($record) => [$record->id => ($record->name[app()->getLocale()] ?? $record->name['en'])])->all())->required(), Repeater::make('items')->label(__('orders.items'))->minItems(1)->maxItems(50)->schema([Select::make('product_id')->label(__('orders.item'))->options(fn () => Product::where('status', 'active')->get()->mapWithKeys(fn ($record) => [$record->id => $record->name])->all())->searchable()->required(), Select::make('variant_id')->label(__('catalog.variant'))->options(fn () => ProductVariant::where('is_active', true)->pluck('sku', 'id'))->searchable(), TextInput::make('qty')->label(__('orders.qty'))->integer()->minValue(1)->maxValue(100)->required()])])->statePath('data');
    }

    public function submit(): void
    {
        $o = app(CreateManualOrder::class)->execute($this->form->getState());
        $this->redirect(OrderResource::getUrl('view', ['record' => $o]));
    }
}
