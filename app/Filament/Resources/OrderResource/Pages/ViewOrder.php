<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Domain\Orders\Actions\EditOrderItems;
use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Actions\OrderAccess;
use App\Domain\Orders\Models\Order;
use App\Filament\Resources\OrderResource;
use App\Jobs\SendOrderConfirmation;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected static string $view = 'filament.orders.view';

    public function getTitle(): string
    {
        return $this->order()->number;
    }

    public function order(): Order
    {
        return Order::with(['items', 'events', 'shipments', 'returns', 'payments', 'refunds'])->findOrFail($this->getRecord()->getKey());
    }

    /** @return array<Component> */
    private function quantities(): array
    {
        return $this->order()->items->map(fn ($i) => TextInput::make('items.'.$i->id)->label($i->name.' / '.$i->sku)->integer()->minValue(0)->maxValue($i->qty)->default(0)->required())->all();
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        foreach (['invoice', 'packing', 'label'] as $type) {
            $actions[] = Action::make($type)->label(__('orders.'.$type))->url(fn () => URL::temporarySignedRoute('admin.order.document', now()->addMinutes(15), ['order' => $this->order()->id, 'type' => $type]))->openUrlInNewTab();
        }
        $actions[] = Action::make('transition')->label(__('orders.change_status'))->form([Select::make('status')->label(__('orders.status'))->options(fn () => collect(ManageOrder::TRANSITIONS[$this->order()->status] ?? [])->mapWithKeys(fn ($s) => [$s => __('orders.status_'.$s)])->all())->required(), Textarea::make('note')->label(__('orders.note'))->maxLength(2000)])->action(fn (array $data) => app(ManageOrder::class)->transition($this->order(), $data['status'], $data['note'] ?? null));
        $actions[] = Action::make('note')->label(__('orders.add_note'))->form([Textarea::make('note')->label(__('orders.note'))->required()->maxLength(2000), Toggle::make('public')->label(__('orders.is_public'))])->action(fn (array $data) => app(ManageOrder::class)->note($this->order(), $data['note'], $data['public']));
        $actions[] = Action::make('payment')->label(__('orders.manual_payment'))->visible(fn () => auth('admin')->user()->can('payments.manage'))->form([TextInput::make('amount')->label(__('orders.amount_minor'))->integer()->minValue(1)->required(), TextInput::make('reference')->label(__('orders.reference'))->required()->maxLength(150), Hidden::make('key')->default(fn () => (string) Str::uuid())])->action(fn (array $data) => app(ManageOrder::class)->payment($this->order(), (int) $data['amount'], $data['reference'], $data['key']));
        $actions[] = Action::make('shipment')->label(__('orders.add_shipment'))->form(fn () => [...$this->quantities(), TextInput::make('carrier')->label(__('orders.carrier'))->required()->maxLength(100), TextInput::make('tracking')->label(__('orders.tracking_no'))->required()->maxLength(150), Hidden::make('key')->default(fn () => (string) Str::uuid())])->action(fn (array $data) => app(ManageOrder::class)->ship($this->order(), array_filter(array_map('intval', $data['items'])), $data['carrier'], $data['tracking'], $data['key']));
        $actions[] = Action::make('edit_items')->label(__('orders.edit_items'))->form(fn () => $this->order()->items->map(fn ($i) => TextInput::make('items.'.$i->id)->label($i->name)->integer()->minValue(1)->maxValue(100)->default($i->qty)->required())->all())->action(fn (array $data) => app(EditOrderItems::class)->execute($this->order(), array_map('intval', $data['items'])));
        $actions[] = Action::make('resolve_return')->label(__('orders.resolve_return'))->visible(fn () => auth('admin')->user()->can('refunds.manage'))->form([Select::make('return_id')->label(__('orders.request_return'))->options(fn () => $this->order()->returns()->where('status', 'requested')->pluck('reason', 'id'))->required(), Toggle::make('approve')->label(__('orders.approve')), Textarea::make('note')->label(__('orders.note'))->maxLength(2000)])->action(function (array $data) {
            $r = $this->order()->returns()->findOrFail($data['return_id']);
            app(ManageOrder::class)->resolveReturn($r, $data['approve'], $data['note'] ?? '');
        });
        $actions[] = Action::make('refund')->label(__('orders.manual_refund'))->visible(fn () => auth('admin')->user()->can('refunds.manage'))->modalDescription(__('orders.refund_notice'))->form(fn () => [TextInput::make('amount')->label(__('orders.amount_minor'))->integer()->minValue(1)->required(), Textarea::make('reason')->label(__('orders.reason'))->required()->maxLength(2000), ...$this->quantities(), Hidden::make('key')->default(fn () => (string) Str::uuid())])->action(fn (array $data) => app(ManageOrder::class)->refund($this->order(), (int) $data['amount'], $data['reason'], $data['key'], array_filter(array_map('intval', $data['items']))));
        $actions[] = Action::make('resend')->label(__('orders.resend'))->action(function () {
            app(OrderAccess::class)->staff();
            DB::table('order_notifications')->where('order_id', $this->order()->id)->update(['sent_at' => null, 'attempts' => 0]);
            SendOrderConfirmation::dispatch($this->order()->id);
            app(ManageOrder::class)->event($this->order(), 'resend', __('orders.resend'));
        });

        return [ActionGroup::make(array_slice($actions, 0, 3))->label(__('orders.documents'))->icon('heroicon-o-document-text')->button(), ActionGroup::make(array_slice($actions, 3))->label(__('orders.manage_order'))->icon('heroicon-o-cog-6-tooth')->button()];
    }
}
