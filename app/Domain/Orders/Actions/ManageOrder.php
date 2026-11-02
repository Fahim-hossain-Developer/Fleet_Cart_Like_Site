<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Orders\Events\OrderChanged;
use App\Domain\Orders\Models\DownloadEntitlement;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderPayment;
use App\Domain\Orders\Models\OrderRefund;
use App\Domain\Orders\Models\OrderReturn;
use App\Domain\Orders\Models\Shipment;
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
            if (in_array($o->status, ['cancelled', 'failed', 'returned', 'refunded']) || $amount > $o->total - $o->paid_amount) {
                $this->fail();
            }
            if ($o->stock_policy === 'payment') {
                app(OrderInventory::class)->deduct($o);
            }
            $o->payments()->create(['key' => $key, 'amount' => $amount, 'reference' => $reference, 'admin_id' => $admin->id]);
            $paid = $o->paid_amount + $amount;
            $o->update(['paid_amount' => $paid, 'payment_status' => $paid === $o->total ? 'paid' : 'partial']);
            if ($paid === $o->total) {
                foreach ($o->items as $item) {
                    DownloadEntitlement::where('order_item_id', $item->id)->whereNull('enabled_at')->get()->each(fn ($e) => $e->update(['enabled_at' => now(), 'expires_at' => $e->expiry_days ? now()->addDays($e->expiry_days) : null]));
                }
            }
            $this->event($o, 'payment', __('orders.payment_recorded'), true);
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
            if (! in_array($o->status, ['packed', 'shipped'])) {
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
            $old = OrderRefund::where('key', $key)->first();
            if ($old) {
                abort_unless($old->order_id === $o->id, 403);

                return;
            }
            $shipped = $o->shipments()->exists();
            $r = $o->returns()->where('status', 'approved')->first();
            if (($shipped && ! $r) || (! $shipped && ! in_array($o->status, ['cancelled', 'failed'])) || $amount > $o->paid_amount - $o->refunded_amount) {
                $this->fail();
            }
            // Before shipping, full cancellation includes shipping/gift/tax. After shipping only approved returned merchandise is refundable.
            $cap = $o->total;
            if ($shipped) {
                $cap = 0;
                foreach ($r->items as $id => $qty) {
                    $i = $o->items()->findOrFail($id);
                    $cap += intdiv($i->total * $qty, $i->qty);
                }if ($o->refunded_amount + $amount > $cap) {
                    $this->fail();
                }
            }
            foreach ($restock as $id => $qty) {
                $i = $o->items()->find($id);
                if (! $i || ($shipped && $qty > ($r->items[$id] ?? 0))) {
                    $this->fail();
                }$already = 0;
                foreach ($o->refunds as $previous) {
                    $already += (int) ($previous->items[$id] ?? 0);
                }
                if ($shipped && $qty + $already > ($r->items[$id] ?? 0)) {
                    $this->fail();
                }
                app(OrderInventory::class)->restock($i, $qty);
            }
            $o->refunds()->create(['key' => $key, 'amount' => $amount, 'reason' => $reason, 'items' => $restock, 'admin_id' => $admin->id]);
            $refunded = $o->refunded_amount + $amount;
            $o->update(['refunded_amount' => $refunded, 'payment_status' => $refunded === $o->paid_amount ? 'refunded' : 'partial_refund']);
            if (! $shipped && $refunded === $o->total) {
                $o->update(['status' => 'refunded']);
            }
            if ($shipped && $refunded === $cap) {
                $r->update(['status' => 'refunded']);
                $o->update(['status' => 'returned']);
            }
            $this->event($o, 'refund', __('orders.manual_refund_recorded'), true);
        }, 3);
    }
}
