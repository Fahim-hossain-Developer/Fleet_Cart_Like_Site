<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Actions\OrderInventory;
use App\Domain\Orders\Models\DownloadEntitlement;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordPaymentReceipt
{
    // Internal receipt boundary. Caller holds the order lock and authorizes staff or verifies the provider first.
    public function execute(Order $o, int $amount, string $reference, string $key, ?int $actorId, string $gateway = 'manual', ?string $providerReference = null, ?string $mode = null): OrderPayment
    {
        $old = OrderPayment::where('key', $key)->first();
        if ($old) {
            abort_unless($old->order_id === $o->id, 403);

            return $old;
        }
        if (app()->environment('production') && ($mode === 'sandbox' || $o->payment_environment === 'sandbox')) {
            throw ValidationException::withMessages(['order' => __('orders.invalid_operation')]);
        }
        if ($gateway === 'manual') {
            app(PendingOperations::class)->mutable($o);
        }
        if (in_array($o->status, ['cancelled', 'failed', 'returned', 'refunded']) || $amount < 1 || $amount > $o->total - $o->paid_amount) {
            throw ValidationException::withMessages(['order' => __('orders.invalid_operation')]);
        }
        if ($o->stock_policy === 'payment') {
            try {
                DB::transaction(fn () => app(OrderInventory::class)->deduct($o));
            } catch (ValidationException $e) {
                if ($gateway === 'manual') {
                    throw $e;
                }$flags = $o->risk_flags ?? [];
                $flags[] = 'paid_stock_unavailable';
                $o->update(['status' => 'on-hold', 'risk_flags' => array_values(array_unique($flags))]);
                app(ManageOrder::class)->event($o, 'inventory_hold', __('payments.paid_inventory_hold'), true);
            }
        }
        $payment = $o->payments()->create(['key' => $key, 'amount' => $amount, 'reference' => $reference, 'admin_id' => $actorId, 'gateway' => $gateway, 'provider_reference' => $providerReference]);
        $paid = $o->paid_amount + $amount;
        $o->update(['paid_amount' => $paid, 'payment_status' => $paid === $o->total ? 'paid' : 'partial', 'payment_environment' => ($mode === 'sandbox' || $o->payment_environment === 'sandbox') ? 'sandbox' : ($mode ?? $o->payment_environment)]);
        if ($paid === $o->total) {
            foreach ($o->items as $item) {
                foreach (DownloadEntitlement::where('order_item_id', $item->id)->whereNull('enabled_at')->get() as $e) {
                    $e->update(['enabled_at' => now(), 'expires_at' => $e->expiry_days ? now()->addDays($e->expiry_days) : null]);
                }
            }
        }
        app(ManageOrder::class)->event($o, 'payment', $gateway === 'manual' ? __('orders.payment_recorded') : ($mode === 'sandbox' ? __('payments.test_payment_recorded') : __('payments.provider_payment_verified')), true);

        return $payment;
    }
}
