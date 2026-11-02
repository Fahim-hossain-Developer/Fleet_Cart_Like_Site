<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\DTOs\RefundResult;
use App\Domain\Payments\DTOs\RemoteState;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Payments\Models\GatewayRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RefundEngine
{
    /**
     * @param array<int,int> $restock */
    public function start(GatewayAttempt $payment, int $amount, string $reason, string $key, array $restock = []): GatewayRefund
    {
        $admin = app(PaymentAccess::class)->staff('refunds.manage');
        validator(compact('amount', 'reason', 'key'), ['amount' => 'required|integer|min:1', 'reason' => 'required|string|max:2000', 'key' => 'required|uuid'])->validate();
        $refund = DB::transaction(function () use ($payment, $amount, $reason, $key, $restock, $admin) {
            $o = Order::lockForUpdate()->findOrFail($payment->order_id);
            $a = GatewayAttempt::lockForUpdate()->findOrFail($payment->id);
            $old = GatewayRefund::where('idempotency_key', $key)->first();
            if ($old) {
                abort_unless($old->gateway_attempt_id === $a->id, 403);

                return $old;
            }
            if (! $a->verified_at || ! $a->verified_transaction || ! in_array($a->status, ['succeeded', 'review_required']) || (app()->environment('production') && $a->mode !== 'live') || ! app(GatewayManager::class)->driver($a->driver, $a->mode)->supportsRefunds()) {
                $this->invalid();
            }
            app(PendingOperations::class)->refundFree($o);
            $reserved = GatewayRefund::where('gateway_attempt_id', $a->id)->whereNotIn('status', ['failed', 'cancelled'])->sum('amount');
            $received = $a->verified_amount ?? 0;
            if ($amount > $received - $reserved) {
                $this->invalid();
            }
            if ($a->order_payment_id) {
                app(RefundRules::class)->validate($o, $amount, $restock);
            } elseif ($restock) {
                $this->invalid();
            }

            return GatewayRefund::create(['uuid' => (string) Str::uuid(), 'idempotency_key' => $key, 'order_id' => $o->id, 'gateway_attempt_id' => $a->id, 'gateway' => $a->driver.':'.$a->mode, 'admin_id' => $admin->id, 'merchant_reference' => 'RF-'.(string) Str::ulid(), 'mode' => $a->mode, 'currency' => $a->verified_currency ?? $a->currency, 'amount' => $amount, 'reason' => $reason, 'restock_items' => $restock, 'status' => 'created']);
        }, 3);
        $this->submit($refund);

        return $refund->refresh();
    }

    public function submit(GatewayRefund $refund): void
    {
        if (! GatewayRefund::whereKey($refund->id)->where('status', 'created')->update(['status' => 'initializing', 'lease_until' => now()->addMinutes(2)])) {
            return;
        }$refund->refresh();
        $a = GatewayAttempt::findOrFail($refund->gateway_attempt_id);
        try {
            $result = app(GatewayManager::class)->driver($a->driver, $refund->mode)->refund($refund);
            $this->apply($refund, $result);
        } catch (\Throwable $e) {
            $refund->update(['status' => 'unknown', 'lease_until' => null, 'failure_code' => $e instanceof IntegrationFailure ? $e->reason : 'unexpected_provider_error']);
        }
    }

    public function attachReference(GatewayRefund $refund, string $reference): void
    {
        app(PaymentAccess::class)->staff('refunds.manage');
        validator(compact('reference'), ['reference' => 'required|string|max:191'])->validate();
        DB::transaction(function () use ($refund, $reference) {
            $o = Order::lockForUpdate()->findOrFail($refund->order_id);
            $r = GatewayRefund::lockForUpdate()->findOrFail($refund->id);
            $a = GatewayAttempt::findOrFail($r->gateway_attempt_id);
            // SSLCommerz status omits amount: only an API-issued response already bound to our request is sufficient.
            if (! in_array($a->driver, ['stripe', 'paypal', 'bkash'], true) || $r->provider_reference || ! in_array($r->status, ['unknown', 'initializing', 'pending'], true) || ($r->lease_until && now()->lt($r->lease_until))) {
                $this->invalid();
            }
            if (GatewayRefund::where('gateway', $a->driver.':'.$r->mode)->where('provider_reference', $reference)->exists()) {
                $this->invalid();
            }
            $r->update(['provider_reference' => $reference]);
            app(ManageOrder::class)->event($o, 'refund_reference', __('integrations.reference_attached'), false);
        }, 3);
        $this->verify($refund->refresh());
    }

    public function verify(GatewayRefund $refund): GatewayRefund
    {
        if (in_array($refund->status, ['succeeded', 'review_required'])) {
            return $refund;
        }
        if (! GatewayRefund::whereKey($refund->id)->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<', now()))->whereNotIn('status', ['succeeded', 'review_required'])->update(['lease_until' => now()->addMinutes(2)])) {
            return $refund;
        }
        $refund->refresh();
        $a = GatewayAttempt::findOrFail($refund->gateway_attempt_id);
        try {
            $result = app(GatewayManager::class)->driver($a->driver, $refund->mode)->verifyRefund($refund);
            $this->apply($refund, $result);
        } catch (\Throwable $e) {
            $refund->update(['status' => 'unknown', 'lease_until' => null, 'failure_code' => $e instanceof IntegrationFailure ? $e->reason : 'unexpected_provider_error']);
        }

        return $refund->refresh();
    }

    private function apply(GatewayRefund $refund, RefundResult $result): void
    {
        DB::transaction(function () use ($refund, $result) {
            $o = Order::lockForUpdate()->findOrFail($refund->order_id);
            $r = GatewayRefund::lockForUpdate()->findOrFail($refund->id);
            if ($r->status === 'succeeded') {
                return;
            }$a = GatewayAttempt::lockForUpdate()->findOrFail($r->gateway_attempt_id);
            $r->fill(['lease_until' => null]);
            if ($result->reference && (! $r->provider_reference || $r->provider_reference === $result->reference)) {
                $r->provider_reference = $result->reference;
            }
            if ($result->state === RemoteState::Paid) {
                $r->fill(['verified_at' => now(), 'verified_amount' => $result->amount, 'verified_currency' => $result->currency, 'verified_reference' => $result->reference]);
                if ($result->amount !== $r->amount || $result->currency !== $r->currency || ! $result->reference || $r->provider_reference !== $result->reference) {
                    $r->fill(['status' => 'review_required', 'failure_code' => 'refund_verification_mismatch'])->save();

                    return;
                }
                if ($a->order_payment_id) {
                    $receipt = app(RecordRefundReceipt::class)->execute($o, $r->amount, $r->reason, hash('sha256', 'gateway-refund:'.$r->uuid), $r->admin_id, $r->restock_items, $a->driver.':'.$r->mode, $result->reference, $r->id);
                    $r->order_refund_id = $receipt->id;
                } else {
                    app(ManageOrder::class)->event($o, 'unapplied_refund', __('payments.unapplied_refund_verified'), false);
                }
                $r->fill(['status' => 'succeeded', 'failure_code' => null])->save();
                if (! $a->order_payment_id && GatewayRefund::where('gateway_attempt_id', $a->id)->where('status', 'succeeded')->sum('amount') >= ($a->verified_amount ?? 0)) {
                    $a->update(['status' => 'refunded_unapplied']);
                }
            } else {
                $r->fill(['status' => in_array($result->state, [RemoteState::Failed, RemoteState::Cancelled], true) ? 'failed' : 'pending', 'failure_code' => null])->save();
            }
        }, 3);
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['refund' => __('payments.refund_unavailable')]);
    }
}
