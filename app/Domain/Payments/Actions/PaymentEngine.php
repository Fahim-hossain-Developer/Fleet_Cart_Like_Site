<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Actions\OrderInventory;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderPayment;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\RemoteState;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Settings\Actions\IntegrationConfiguration;
use App\Domain\Shipping\Models\CourierBooking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PaymentEngine
{
    public function start(Order $order, string $driver, string $key, string $purpose = 'due', ?string $email = null): GatewayAttempt
    {
        app(PaymentAccess::class)->customer($order);
        validator(compact('key', 'purpose', 'email'), ['key' => 'required|uuid', 'purpose' => 'in:due,full', 'email' => 'nullable|email:rfc|max:254'])->validate();
        $config = app(IntegrationConfiguration::class);
        $connection = $config->connection($driver);
        $attempt = DB::transaction(function () use ($order, $driver, $key, $purpose, $connection, $email) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            if (app()->environment('production') && $o->payment_environment === 'sandbox') {
                $this->invalid('payment_unavailable');
            }
            $old = GatewayAttempt::where('idempotency_key', $key)->first();
            if ($old) {
                abort_unless($old->order_id === $o->id, 403);

                return $old;
            }
            if (! in_array($o->status, ['pending', 'confirmed', 'processing', 'packed', 'on-hold']) || $o->shipments()->exists() || CourierBooking::where('order_id', $o->id)->whereNotIn('status', ['failed', 'cancelled'])->exists()) {
                $this->invalid('payment_unavailable');
            }
            $active = GatewayAttempt::where('order_id', $o->id)->whereIn('status', PendingOperations::PAYMENTS)->first();
            if ($active) {
                if ($active->driver !== $driver) {
                    $this->invalid('pending_operation');
                }

                return $active;
            }
            if (! $o->guest_email && $email) {
                $o->update(['guest_email' => strtolower(trim($email))]);
            }if (in_array($driver, ['sslcommerz', 'aamarpay']) && ! $o->guest_email) {
                $this->invalid('email_required');
            }
            $remaining = $o->total - $o->paid_amount;
            $advance = max(0, $o->advance_required - ($o->paid_amount - $o->refunded_amount));
            $amount = $purpose === 'due' && $advance > 0 ? min($remaining, $advance) : $remaining;
            if ($amount < 1 || ! isset(app(GatewayManager::class)->available($o->currency, $amount)[$driver])) {
                $this->invalid('payment_unavailable');
            }
            $uuid = (string) Str::uuid();

            return GatewayAttempt::create(['uuid' => $uuid, 'idempotency_key' => $key, 'order_id' => $o->id, 'driver' => $driver, 'mode' => $connection->mode, 'merchant_reference' => 'PAY-'.substr((string) Str::ulid(), 0, 26), 'currency' => $o->currency, 'amount' => $amount, 'status' => 'created']);
        }, 3);
        $this->initiate($attempt);

        return $attempt->refresh();
    }

    public function initiate(GatewayAttempt $attempt): void
    {
        if (! GatewayAttempt::whereKey($attempt->id)->where('status', 'created')->update(['status' => 'initializing', 'lease_until' => now()->addMinutes(2)])) {
            return;
        }
        $attempt->refresh();
        try {
            $return = app(CallbackToken::class)->url($attempt);
            $webhook = route('integrations.payment.webhook', ['driver' => $attempt->driver, 'mode' => $attempt->mode]);
            $response = app(GatewayManager::class)->driver($attempt->driver, $attempt->mode)->initiate($attempt, $return, $webhook);
            if (! app(GatewayManager::class)->redirectAllowed($attempt->driver, $response->url)) {
                throw new IntegrationFailure($attempt->driver, 'unsafe_redirect');
            }
            $attempt->update(['provider_reference' => $response->reference, 'redirect_url' => $response->url, 'status' => 'initiated', 'lease_until' => null, 'failure_code' => null]);
        } catch (\Throwable $e) {
            $attempt->update(['status' => 'unknown', 'lease_until' => null, 'failure_code' => $e instanceof IntegrationFailure ? $e->reason : 'unexpected_provider_error']);
        }
    }

    public function verify(GatewayAttempt $attempt, bool $allowExecute = false): GatewayAttempt
    {
        $attempt->refresh();
        if (in_array($attempt->status, ['succeeded', 'review_required', 'refunded_unapplied'])) {
            return $attempt;
        }
        $claimed = GatewayAttempt::whereKey($attempt->id)->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<', now()))->whereNotIn('status', ['succeeded', 'review_required', 'refunded_unapplied'])->update(['lease_until' => now()->addMinutes(2)]);
        if (! $claimed) {
            return $attempt;
        }
        $attempt->refresh();
        try {
            $result = app(GatewayManager::class)->driver($attempt->driver, $attempt->mode)->verify($attempt, $allowExecute);
            if ($result->amount < 0 || $result->amount > 100000000000 || ! preg_match('/^[A-Z]{3}$/D', $result->currency) || strlen($result->transactionReference) > 191) {
                throw new IntegrationFailure($attempt->driver, 'invalid_response');
            }
        } catch (\Throwable $e) {
            $attempt->update(['status' => 'unknown', 'lease_until' => null, 'failure_code' => $e instanceof IntegrationFailure ? $e->reason : 'unexpected_provider_error']);

            return $attempt;
        }

        return DB::transaction(function () use ($attempt, $result) {
            $o = Order::lockForUpdate()->findOrFail($attempt->order_id);
            $a = GatewayAttempt::lockForUpdate()->findOrFail($attempt->id);
            if ($a->status === 'succeeded') {
                return $a;
            }
            $a->fill(['lease_until' => null]);
            if (! $result->merchantVerified || $result->merchantReference !== $a->merchant_reference) {
                $a->fill(['status' => 'unknown', 'failure_code' => 'verification_mismatch'])->save();

                return $a;
            }
            if ($result->state === RemoteState::Paid) {
                return $this->credit($o, $a, $result);
            }
            if (in_array($result->state, [RemoteState::Failed, RemoteState::Cancelled], true)) {
                $a->fill(['status' => $result->state->value, 'failure_code' => null])->save();
                if (! $o->paid_amount && ! $o->shipments()->exists() && in_array($o->status, ['pending', 'confirmed', 'processing', 'packed', 'on-hold'])) {
                    foreach ($o->items()->orderBy('product_id')->orderBy('id')->get() as $item) {
                        app(OrderInventory::class)->restock($item, $item->stock_deducted - $item->restocked_qty);
                    }$o->update(['status' => 'failed']);
                    app(ManageOrder::class)->event($o, 'payment_failed', __('payments.payment_failed'), true);
                }
            } else {
                if (! in_array($a->status, ['failed', 'cancelled'])) {
                    $a->fill(['status' => $result->state === RemoteState::Unknown ? 'unknown' : 'initiated', 'failure_code' => null]);
                }$a->save();
            }

            return $a;
        }, 3);
    }

    private function credit(Order $o, GatewayAttempt $a, PaymentResult $result): GatewayAttempt
    {
        $a->fill(['verified_amount' => $result->amount, 'verified_currency' => $result->currency, 'verified_transaction' => $result->transactionReference, 'verified_at' => now()]);
        if ($result->amount !== $a->amount || $result->currency !== $a->currency || ! $result->transactionReference || $o->currency !== $a->currency || $result->amount > $o->total - $o->paid_amount || in_array($o->status, ['cancelled', 'failed', 'returned', 'refunded']) || (app()->environment('production') && ($a->mode !== 'live' || $o->payment_environment === 'sandbox'))) {
            $a->fill(['status' => 'review_required', 'failure_code' => 'received_payment_needs_review'])->save();
            app(ManageOrder::class)->event($o, 'payment_review', __('payments.payment_review'), false);

            return $a;
        }
        $duplicate = OrderPayment::where('gateway', $a->driver.':'.$a->mode)->where('provider_reference', $result->transactionReference)->first();
        if ($duplicate && ($duplicate->order_id !== $o->id || $duplicate->key !== hash('sha256', 'gateway:'.$a->uuid))) {
            $a->fill(['status' => 'review_required', 'failure_code' => 'duplicate_transaction'])->save();

            return $a;
        }
        if ($result->riskReview) {
            $flags = $o->risk_flags ?? [];
            $flags[] = 'gateway_high_risk';
            $o->update(['status' => 'on-hold', 'risk_flags' => array_values(array_unique($flags))]);
        }
        $payment = app(RecordPaymentReceipt::class)->execute($o, $result->amount, $a->driver.': '.$result->transactionReference, hash('sha256', 'gateway:'.$a->uuid), null, $a->driver.':'.$a->mode, $result->transactionReference, $a->mode);
        $a->fill(['status' => 'succeeded', 'order_payment_id' => $payment->id, 'applied_at' => now(), 'failure_code' => null])->save();

        return $a;
    }

    private function invalid(string $key): never
    {
        throw ValidationException::withMessages(['payment' => __('payments.'.$key)]);
    }
}
