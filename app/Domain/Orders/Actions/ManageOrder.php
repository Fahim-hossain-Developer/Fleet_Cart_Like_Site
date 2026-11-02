<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Events\OrderChanged;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderPayment;
use App\Domain\Orders\Models\OrderReturn;
use App\Domain\Orders\Models\Shipment;
use App\Domain\Payments\Actions\PendingOperations;
use App\Domain\Payments\Actions\RecordPaymentReceipt;
use App\Domain\Payments\Actions\RecordRefundReceipt;
use App\Domain\Shipping\Models\CourierBooking;
use App\Jobs\SendOrderUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ManageOrder
{
    public const TRANSITIONS = ['pending' => ['confirmed', 'on-hold', 'cancelled', 'failed'], 'on-hold' => ['confirmed', 'cancelled', 'failed'], 'confirmed' => ['processing', 'on-hold', 'cancelled'], 'processing' => ['packed', 'on-hold', 'cancelled'], 'packed' => ['on-hold', 'cancelled'], 'shipped' => ['delivered'], 'delivered' => ['completed'], 'completed' => [], 'cancelled' => [], 'failed' => [], 'returned' => [], 'refunded' => []];

    public function event(Order $o, string $type, string $note, bool $public = false): void
    {
        $event = $o->events()->create(['type' => $type, 'note' => $note, 'is_public' => $public, 'admin_id' => auth('admin')->id(), 'notify' => $public]);
        OrderChanged::dispatch($o->id, $type);
        if ($public) {
            DB::afterCommit(function () use ($event) {
                try {
                    SendOrderUpdate::dispatch($event->id);
                } catch (\Throwable $e) {
                    Log::warning('Order update pending retry', ['event_id' => $event->id]);
                }
            });
        }
    }

    private function fail(): never
    {
        throw ValidationException::withMessages(['order' => __('orders.invalid_operation')]);
    }

    public function transition(Order $order, string $status, ?string $note = null, bool $customer = false): Order
    {
        if ($customer) {
            app(OrderAccess::class)->owner($order);
        } else {
            app(OrderAccess::class)->staff();
        }

        return DB::transaction(function () use ($order, $status, $note, $customer) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            if ($customer && ($o->status !== 'pending' || $status !== 'cancelled')) {
                $this->fail();
            }
            if (in_array($status, ['cancelled', 'failed'])) {
                app(PendingOperations::class)->mutable($o);
            }
            if ($o->status === $status) {
                return $o;
            }
            if (! in_array($status, self::TRANSITIONS[$o->status] ?? [], true)) {
                $this->fail();
            }
            if (in_array($status, ['cancelled', 'failed']) && $o->shipments()->exists()) {
                $this->fail();
            }
            if ($status === 'confirmed' && $o->stock_policy === 'confirmation') {
                app(OrderInventory::class)->deduct($o);
            }
            if (in_array($status, ['cancelled', 'failed'])) {
                foreach ($o->items()->orderBy('product_id')->orderBy('id')->get() as $item) {
                    app(OrderInventory::class)->restock($item, $item->stock_deducted - $item->restocked_qty);
                }
            }
            if ($status === 'completed' && $o->payment_status !== 'paid') {
                $this->fail();
            }
            if ($status === 'delivered' && $o->fulfillment_status !== 'fulfilled') {
                $this->fail();
            }
            if ($status === 'delivered') {
                $o->shipments()->update(['status' => 'delivered']);
            }
            $o->update(['status' => $status]);
            $this->event($o, $status, __('orders.status_'.$status).($note ? ': '.$note : ''), true);

            return $o;
        }, 3);
    }

    public function note(Order $order, string $note, bool $public): void
    {
        app(OrderAccess::class)->staff();
        validator(['note' => $note], ['note' => 'required|string|max:2000'])->validate();
        $this->event($order, 'note', $note, $public);
    }

    public function payment(Order $order, int $amount, string $reference, string $key): void
    {
        $admin = app(OrderAccess::class)->staff('payments.manage');
        validator(compact('amount', 'reference', 'key'), ['amount' => 'required|integer|min:1|max:100000000000', 'reference' => 'required|string|max:150', 'key' => 'required|uuid'])->validate();
        DB::transaction(function () use ($order, $amount, $reference, $key, $admin) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            $existing = OrderPayment::where('key', $key)->first();
            if ($existing) {
                abort_unless($existing->order_id === $o->id, 403);

                return;
            }
            app(RecordPaymentReceipt::class)->execute($o, $amount, $reference, $key, $admin->id);
        }, 3);
    }

    /** @param array<int,int> $items */
    public function ship(Order $order, array $items, string $carrier, string $tracking, string $key): void
    {
        app(OrderAccess::class)->staff();
        validator(compact('carrier', 'tracking', 'key'), ['carrier' => 'required|string|max:100', 'tracking' => 'required|string|max:150', 'key' => 'required|uuid'])->validate();
        DB::transaction(function () use ($order, $items, $carrier, $tracking, $key) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            $old = Shipment::where('key', $key)->first();
            if ($old) {
                abort_unless($old->order_id === $o->id, 403);

                return;
            }
            app(PendingOperations::class)->mutable($o);
            if (app()->environment('production') && $o->payment_environment === 'sandbox') {
                $this->fail();
            }
            if ($o->payment_method === 'cod' && $o->paid_amount - $o->refunded_amount < $o->advance_required) {
                $this->fail();
            }
            if (CourierBooking::where('order_id', $o->id)->exists() || ! in_array($o->status, ['packed', 'shipped'])) {
                $this->fail();
            }
            app(OrderInventory::class)->deduct($o);
            $sent = [];
            foreach ($o->shipments as $s) {
                foreach ($s->items as $id => $qty) {
                    $sent[$id] = ($sent[$id] ?? 0) + $qty;
                }
            }
            if (! array_sum($items)) {
                $this->fail();
            }
            foreach ($items as $id => $qty) {
                $item = $o->items()->find($id);
                if (! $item || $qty < 1 || $qty > $item->qty - ($sent[$id] ?? 0)) {
                    $this->fail();
                }$sent[$id] = ($sent[$id] ?? 0) + $qty;
            }
            $o->shipments()->create(['key' => $key, 'carrier' => $carrier, 'tracking_no' => $tracking, 'items' => $items]);
            $complete = $o->items->every(fn ($i) => ($sent[$i->id] ?? 0) === $i->qty);
            $o->update(['status' => 'shipped', 'fulfillment_status' => $complete ? 'fulfilled' : 'partial']);
            $this->event($o, 'shipment', __('orders.shipped').': '.$carrier.' / '.$tracking, true);
        }, 3);
    }

    /** @param array<int,int> $items */
    public function requestReturn(Order $order, array $items, string $reason): void
    {
        app(OrderAccess::class)->owner($order);
        validator(['reason' => $reason], ['reason' => 'required|string|max:2000'])->validate();
        DB::transaction(function () use ($order, $items, $reason) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            if (! in_array($o->status, ['delivered', 'completed']) || $o->returns()->whereIn('status', ['requested', 'approved', 'refunded'])->exists() || ! array_sum($items)) {
                $this->fail();
            }
            foreach ($items as $id => $qty) {
                $i = $o->items()->find($id);
                if (! $i || $qty < 1 || $qty > $i->qty) {
                    $this->fail();
                }
            }
            $o->returns()->create(['items' => $items, 'reason' => $reason]);
            $this->event($o, 'return', __('orders.return_requested'), true);
        }, 3);
    }

    public function resolveReturn(OrderReturn $return, bool $approve, string $note): void
    {
        app(OrderAccess::class)->staff('refunds.manage');
        DB::transaction(function () use ($return, $approve, $note) {
            $o = Order::lockForUpdate()->findOrFail($return->order_id);
            $r = OrderReturn::lockForUpdate()->findOrFail($return->id);
            if ($r->status !== 'requested') {
                $this->fail();
            }$r->update(['status' => $approve ? 'approved' : 'rejected', 'staff_note' => $note]);
            $this->event($o, 'return', __('orders.return_'.$r->status), true);
        }, 3);
    }

    /** Manual refund receipt; does not call a gateway.
     * @param array<int,int> $restock */
    public function refund(Order $order, int $amount, string $reason, string $key, array $restock = []): void
    {
        $admin = app(OrderAccess::class)->staff('refunds.manage');
        validator(compact('amount', 'reason', 'key'), ['amount' => 'required|integer|min:1', 'reason' => 'required|string|max:2000', 'key' => 'required|uuid'])->validate();
        DB::transaction(function () use ($order, $amount, $reason, $key, $restock, $admin) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            app(RecordRefundReceipt::class)->execute($o, $amount, $reason, $key, $admin->id, $restock);
        }, 3);
    }
}
