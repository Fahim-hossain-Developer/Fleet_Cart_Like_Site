<?php

namespace App\Domain\Shipping\Actions;

use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Actions\OrderAccess;
use App\Domain\Orders\Actions\OrderInventory;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Shipment;
use App\Domain\Payments\Actions\PendingOperations;
use App\Domain\Payments\Actions\RecordPaymentReceipt;
use App\Domain\Settings\Actions\IntegrationConfiguration;
use App\Domain\Shipping\Couriers\LocalCourier;
use App\Domain\Shipping\DTOs\BookingResult;
use App\Domain\Shipping\Models\CourierBooking;
use App\Domain\Shipping\Models\CourierCollection;
use App\Domain\Shipping\Models\CourierRemittance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CourierEngine
{
    private function fail(): never
    {
        throw ValidationException::withMessages(['order' => __('orders.invalid_operation')]);
    }

    public function book(Order $order, string $driver, string $key, ?string $reference = null, ?string $tracking = null): CourierBooking
    {
        $admin = app(OrderAccess::class)->staff();
        validator(compact('key', 'reference', 'tracking'), ['key' => 'required|uuid', 'reference' => 'nullable|string|max:150', 'tracking' => 'nullable|string|max:150'])->validate();
        $config = app(IntegrationConfiguration::class);
        abort_unless(in_array($driver, ['courier_fake', 'courier_manual'], true) && $config->ready($driver), 422);
        if ($driver === 'courier_manual' && (! $reference || ! $tracking)) {
            $this->fail();
        }
        $c = $config->connection($driver);
        $b = DB::transaction(function () use ($order, $driver, $key, $admin, $c) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            $old = CourierBooking::where('idempotency_key', $key)->first();
            if ($old) {
                abort_unless($old->order_id === $o->id, 403);

                return $old;
            }
            app(PendingOperations::class)->mutable($o);
            app(PendingOperations::class)->refundFree($o);
            if ((app()->environment('production') && $o->payment_environment === 'sandbox') || $o->status !== 'packed' || $o->shipments()->exists() || CourierBooking::where('order_id', $o->id)->exists() || ($o->payment_method === 'cod' && $o->paid_amount - $o->refunded_amount < $o->advance_required) || empty($o->phone) || empty($o->shipping_address['line1'])) {
                $this->fail();
            }
            app(OrderInventory::class)->deduct($o);

            return CourierBooking::create(['uuid' => (string) Str::uuid(), 'idempotency_key' => $key, 'order_id' => $o->id, 'admin_id' => $admin->id, 'driver' => $driver, 'mode' => $c->mode, 'merchant_reference' => 'SHP-'.Str::ulid(), 'items' => $o->items->pluck('qty', 'id')->all(), 'cod_expected' => $o->payment_method === 'cod' ? max(0, $o->total - $o->paid_amount) : 0]);
        }, 3);
        if (! CourierBooking::whereKey($b->id)->where('status', 'created')->update(['status' => 'initializing', 'lease_until' => now()->addMinutes(2)])) {
            return $b->refresh();
        }
        try {
            $result = $driver === 'courier_manual' ? new BookingResult('accepted', $reference, $tracking) : app(LocalCourier::class)->book($b);
            $this->apply($b, $result);
        } catch (\Throwable $e) {
            CourierBooking::whereKey($b->id)->where('status', 'initializing')->update(['status' => 'unknown', 'failure_code' => 'booking_unknown', 'lease_until' => null]);
        }

        return $b->refresh();
    }

    public function query(CourierBooking $booking): void
    {
        app(OrderAccess::class)->staff();
        abort_unless($booking->driver === 'courier_fake' && app()->environment('local', 'testing'), 422);
        $this->apply($booking, app(LocalCourier::class)->query($booking));
    }

    public function confirmManual(CourierBooking $booking, string $reference, string $tracking): void
    {
        app(OrderAccess::class)->staff();
        validator(compact('reference', 'tracking'), ['reference' => 'required|string|max:150', 'tracking' => 'required|string|max:150'])->validate();
        abort_unless($booking->driver === 'courier_manual' && in_array($booking->status, ['unknown', 'initializing'], true) && (! $booking->lease_until || now()->gte($booking->lease_until)), 422);
        $this->apply($booking, new BookingResult('accepted', $reference, $tracking));
    }

    private function apply(CourierBooking $booking, BookingResult $result): void
    {
        DB::transaction(function () use ($booking, $result) {
            $o = Order::lockForUpdate()->findOrFail($booking->order_id);
            $b = CourierBooking::lockForUpdate()->findOrFail($booking->id);
            if (app()->environment('production') && ($b->driver === 'courier_fake' || $b->mode !== 'live')) {
                $this->fail();
            }
            if (! in_array($result->status, ['accepted', 'in_transit', 'delivered', 'returned', 'failed'], true) || ! $result->reference || ! $result->tracking) {
                $this->fail();
            }
            if ($b->provider_reference && $b->provider_reference !== $result->reference) {
                $this->fail();
            }
            if (! $b->shipment_id) {
                if ($o->status !== 'packed' || $o->shipments()->exists()) {
                    $this->fail();
                }
                $s = $o->shipments()->create(['key' => $b->uuid, 'carrier' => $b->driver, 'tracking_no' => $result->tracking, 'items' => $b->items]);
                $b->shipment_id = $s->id;
                $o->update(['status' => 'shipped', 'fulfillment_status' => 'fulfilled']);
                app(ManageOrder::class)->event($o, 'shipment', __('orders.shipped').': '.$b->driver.' / '.$result->tracking, true);
            }
            // Delivered is not proof of collection. Store nullable provider evidence separately.
            $b->fill(['status' => 'accepted', 'provider_status' => $result->status, 'provider_reference' => $result->reference, 'tracking_no' => $result->tracking, 'reported_collected' => $result->collected, 'delivery_fee' => $result->fee, 'last_checked_at' => now(), 'lease_until' => null, 'failure_code' => null])->save();
            if ($result->status === 'delivered' && $o->status === 'shipped') {
                $o->update(['status' => 'delivered']);
                Shipment::whereKey($b->shipment_id)->update(['status' => 'delivered']);
                app(ManageOrder::class)->event($o, 'delivered', __('orders.status_delivered'), true);
            }
        }, 3);
    }

    public function receipt(CourierBooking $booking, int $amount, string $reference, string $key, bool $remittance = false): void
    {
        $admin = app(OrderAccess::class)->staff('payments.manage');
        validator(compact('amount', 'reference', 'key'), ['amount' => 'required|integer|min:1|max:100000000000', 'reference' => 'required|string|max:150', 'key' => 'required|uuid'])->validate();
        DB::transaction(function () use ($booking, $amount, $reference, $key, $remittance, $admin) {
            $o = Order::lockForUpdate()->findOrFail($booking->order_id);
            $b = CourierBooking::lockForUpdate()->findOrFail($booking->id);
            if ($b->status !== 'accepted' || (app()->environment('production') && ($b->mode !== 'live' || $b->driver === 'courier_fake'))) {
                $this->fail();
            }
            $class = $remittance ? CourierRemittance::class : CourierCollection::class;
            $old = $class::where('idempotency_key', $key)->first();
            if ($old) {
                abort_unless($old->courier_booking_id === $b->id, 403);

                return;
            }
            if ($class::where('courier_booking_id', $b->id)->where('reference', $reference)->exists()) {
                $this->fail();
            }
            $collected = (int) CourierCollection::where('courier_booking_id', $b->id)->sum('amount');
            if ($amount > ($remittance ? $collected - $b->remitted_amount : $b->cod_expected - $collected)) {
                $this->fail();
            }
            if ($remittance) {
                $b->increment('remitted_amount', $amount);
            } else {
                app(RecordPaymentReceipt::class)->execute($o, $amount, $reference, hash('sha256', 'courier|'.$key), $admin->id, 'courier_manual', $b->uuid.'|'.$reference, $b->mode);
            }
            $class::create(['courier_booking_id' => $b->id, 'admin_id' => $admin->id, 'idempotency_key' => $key, 'amount' => $amount, 'reference' => $reference]);
            app(ManageOrder::class)->event($o, $remittance ? 'remittance' : 'cod_collection', __('integrations.'.($remittance ? 'remittance' : 'collection')), false);
        }, 3);
    }
}
