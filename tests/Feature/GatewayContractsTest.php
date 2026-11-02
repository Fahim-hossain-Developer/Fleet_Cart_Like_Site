<?php

use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\GatewayManager;
use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Payments\Actions\PaymentEngine;
use App\Domain\Payments\Actions\ProviderHttp;
use App\Domain\Payments\Actions\RefundEngine;
use App\Domain\Payments\DTOs\RemoteState;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Payments\Models\GatewayRefund;
use App\Domain\Settings\Actions\CredentialVault;
use App\Domain\Settings\Actions\IntegrationConfiguration;
use App\Domain\Settings\Models\IntegrationConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function configuredGateway(string $driver): Order
{
    Queue::fake();
    $f = checkoutFixture();
    test()->post('/checkout', array_replace($f['data'], ['email' => 'payer@example.com']))->assertRedirect();
    $o = Order::firstOrFail();
    $v = app(CredentialVault::class);
    foreach (IntegrationConfiguration::PROVIDERS[$driver]['keys'] as $k) {
        $v->replace('integration.'.$driver.'.sandbox.'.$k, 'PRIVATE_'.$k);
    }
    IntegrationConnection::create(['driver' => $driver, 'kind' => 'payment', 'is_enabled' => true, 'mode' => 'sandbox', 'config' => ['minimum' => 1, 'maximum' => 100000000000]]);

    return $o;
}
it('requires SSLCommerz validation after transaction lookup and authenticates IPN fields', function () {
    $o = configuredGateway('sslcommerz');
    Http::fake(function ($r) {
        $a = GatewayAttempt::firstOrFail();
        if (str_contains($r->url(), '/gwprocess/')) {
            return Http::response(['status' => 'SUCCESS', 'sessionkey' => 'SSL-SESSION', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/EasyCheckOut/test']);
        }if (str_contains($r->url(), 'validationserverAPI')) {
            return Http::response(['status' => 'VALID', 'tran_id' => $a->merchant_reference, 'bank_tran_id' => 'SSL-BANK', 'currency_amount' => '1080.00', 'currency_type' => 'BDT', 'risk_level' => 1]);
        }

        return Http::response(['status' => 'VALID', 'val_id' => 'SSL-VALIDATION', 'tran_id' => $a->merchant_reference]);
    });
    $a = app(PaymentEngine::class)->start($o, 'sslcommerz', (string) Str::uuid());
    app(PaymentEngine::class)->verify($a);
    expect($a->refresh()->status)->toBe('succeeded')->and($o->refresh()->risk_flags)->toContain('gateway_high_risk');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'validationserverAPI') && $r['val_id'] === 'SSL-VALIDATION');
    $data = ['tran_id' => $a->merchant_reference, 'val_id' => 'SSL-VALIDATION', 'amount' => '1080.00', 'currency' => 'BDT'];
    $keys = array_keys($data);
    $signed = $data + ['store_passwd' => md5('PRIVATE_store_password')];
    ksort($signed);
    $parts = [];
    foreach ($signed as $k => $v) {
        $parts[] = $k.'='.$v;
    }
    $request = Request::create('/', 'POST', $data + ['verify_key' => implode(',', $keys), 'verify_sign' => md5(implode('&', $parts))]);
    $g = app(GatewayManager::class)->driver('sslcommerz');
    expect($g->authenticateWebhook($request))->toBeTrue();
    $request->request->set('amount', '1.00');
    expect($g->authenticateWebhook($request))->toBeFalse();
});
it('treats mismatched verified amounts as review funds without crediting or releasing stock', function () {
    $o = configuredGateway('stripe');
    Http::fake(function ($r) {
        $a = GatewayAttempt::firstOrFail();

        return Http::response($r->method() === 'POST' ? ['id' => 'cs_mismatch', 'url' => 'https://checkout.stripe.com/pay/test'] : ['id' => 'cs_mismatch', 'livemode' => false, 'client_reference_id' => $a->merchant_reference, 'payment_intent' => 'pi_mismatch', 'amount_total' => 108001, 'currency' => 'bdt', 'payment_status' => 'paid']);
    });
    $a = app(PaymentEngine::class)->start($o, 'stripe', (string) Str::uuid());
    app(PaymentEngine::class)->verify($a);
    expect($a->refresh()->status)->toBe('review_required')->and($a->verified_amount)->toBe(108001)->and($o->refresh()->paid_amount)->toBe(0);
});
it('uses current bKash V2 and does not repeat an execute after an uncertain transport result', function () {
    $o = configuredGateway('bkash');
    $execute = 0;
    Http::fake(function ($r) use (&$execute) {
        $a = GatewayAttempt::firstOrFail();
        if (str_contains($r->url(), '/grant-token')) {
            return Http::response(['id_token' => 'PRIVATE_TOKEN', 'refresh_token' => 'PRIVATE_REFRESH', 'expires_in' => 3600]);
        }if (str_ends_with($r->url(), '/payment/create')) {
            return Http::response(['paymentId' => 'BK-PAY', 'bkashURL' => 'https://sandbox.payment.bkash.com/test', 'signature' => 'PRIVATE_CALLBACK_PROOF']);
        }if (str_ends_with($r->url(), '/payment/execute')) {
            $execute++;
            throw new ConnectionException('PRIVATE_TRANSPORT_SECRET');
        }

        return Http::response(['paymentId' => 'BK-PAY', 'merchantInvoice' => $a->merchant_reference, 'transactionStatus' => 'Initiated', 'verificationStatus' => 'Complete', 'amount' => '1080.00', 'currency' => 'BDT']);
    });
    $a = app(PaymentEngine::class)->start($o, 'bkash', (string) Str::uuid());
    expect($a->status)->toBe('initiated')->and($a->callback_proof_hash)->toBe(hash('sha256', 'PRIVATE_CALLBACK_PROOF'));
    app(PaymentEngine::class)->verify($a, true);
    expect($a->refresh()->status)->toBe('unknown')->and($a->execution_started_at)->not->toBeNull();
    app(PaymentEngine::class)->verify($a, true);
    expect($execute)->toBe(1)->and($o->refresh()->paid_amount)->toBe(0);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/tokenized-checkout/payment/create') && $r['merchantInvoiceNumber'] === $a->merchant_reference && $r['amount'] === '1080.00' && strlen($r['callbackURL']) <= 255);
});
it('verifies aamarPay through its authenticated merchant transaction endpoint and leaves unsigned IPN disabled', function () {
    $o = configuredGateway('aamarpay');
    Http::fake(function ($r) {
        $a = GatewayAttempt::firstOrFail();
        if (str_contains($r->url(), 'jsonpost.php')) {
            return Http::response(['result' => 'true', 'payment_url' => 'https://sandbox.aamarpay.com/pay/test']);
        }

        return Http::response(['store_id' => 'PRIVATE_store_id', 'status_code' => '2', 'mer_txnid' => $a->merchant_reference, 'pg_txnid' => 'AAMAR-TXN', 'amount_currency' => '1080.00', 'currency_merchant' => 'BDT']);
    });
    $a = app(PaymentEngine::class)->start($o, 'aamarpay', (string) Str::uuid());
    app(PaymentEngine::class)->verify($a);
    expect($o->refresh()->payment_status)->toBe('paid');
    $this->post('/integrations/payments/aamarpay/sandbox/webhook', ['status_code' => '2'])->assertForbidden();
});
it('uses PayPal supported currency capture and verifies parent capture on refunds', function () {
    $o = configuredGateway('paypal');
    expect(app(GatewayManager::class)->available('BDT', 108000))->not->toHaveKey('paypal');
    $o->update(['currency' => 'USD']);
    $captured = false;
    Http::fake(function ($r) use (&$captured) {
        $a = GatewayAttempt::firstOrFail();
        if (str_contains($r->url(), 'oauth2/token')) {
            return Http::response(['access_token' => 'PRIVATE_PAYPAL_TOKEN', 'expires_in' => 3600]);
        }if (str_ends_with($r->url(), '/v2/checkout/orders') && $r->method() === 'POST') {
            return Http::response(['id' => 'PP-ORDER', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PP-ORDER']]]);
        }if (str_ends_with($r->url(), '/capture')) {
            $captured = true;

            return Http::response(['id' => 'PP-ORDER']);
        }

        return Http::response(['id' => 'PP-ORDER', 'status' => $captured ? 'COMPLETED' : 'APPROVED', 'purchase_units' => [['custom_id' => $a->merchant_reference, 'amount' => ['currency_code' => 'USD', 'value' => '1080.00'], 'payments' => ['captures' => $captured ? [['id' => 'PP-CAPTURE', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '1080.00']]] : []]]]]);
    });
    $a = app(PaymentEngine::class)->start($o, 'paypal', (string) Str::uuid());
    app(PaymentEngine::class)->verify($a, true);
    expect($a->refresh()->status)->toBe('succeeded')->and($a->verified_transaction)->toBe('PP-CAPTURE');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/PP-ORDER/capture') && $r->hasHeader('PayPal-Request-Id', $a->merchant_reference));
});
it('retains unknown refund reservations until an exact provider response confirms success', function () {
    $o = configuredGateway('stripe');
    Http::fake(function ($r) {
        $a = GatewayAttempt::firstOrFail();
        if (str_contains($r->url(), '/refunds')) {
            $refund = GatewayRefund::firstOrFail();

            return Http::response(['id' => 're_pending', 'payment_intent' => 'pi_refund', 'amount' => 108000, 'currency' => 'bdt', 'status' => $r->method() === 'POST' ? 'pending' : 'succeeded', 'metadata' => ['merchant_reference' => $refund->merchant_reference]]);
        }

        return Http::response($r->method() === 'POST' ? ['id' => 'cs_refund', 'url' => 'https://checkout.stripe.com/pay/test'] : ['id' => 'cs_refund', 'livemode' => false, 'client_reference_id' => $a->merchant_reference, 'payment_intent' => 'pi_refund', 'amount_total' => 108000, 'currency' => 'bdt', 'payment_status' => 'paid']);
    });
    $a = app(PaymentEngine::class)->start($o, 'stripe', (string) Str::uuid());
    app(PaymentEngine::class)->verify($a);
    test()->actingAs(catalogAdmin(), 'admin');
    app(ManageOrder::class)->transition($o, 'cancelled');
    $refund = app(RefundEngine::class)->start($a->refresh(), 108000, 'Cancelled', (string) Str::uuid());
    expect($refund->status)->toBe('pending')->and($o->refresh()->refunded_amount)->toBe(0);
    expect(fn () => app(ManageOrder::class)->refund($o, 1, 'Concurrent', (string) Str::uuid()))->toThrow(ValidationException::class);
    app(RefundEngine::class)->verify($refund);
    app(RefundEngine::class)->verify($refund->refresh());
    expect($refund->refresh()->status)->toBe('succeeded')->and($o->refresh()->refunded_amount)->toBe(108000)->and($o->refunds()->count())->toBe(1);
});
it('redacts raw HTTP exceptions and rejects unsafe redirects', function () {
    Http::fake(fn () => throw new ConnectionException('https://provider.test?key=SECRET_VALUE'));
    try {
        app(ProviderHttp::class)->json('stripe', fn ($h) => $h->post('https://provider.test'));
        test()->fail();
    } catch (IntegrationFailure $e) {
        expect($e->getMessage())->not->toContain('SECRET_VALUE')->and($e->getPrevious())->toBeNull();
    }
    $g = app(GatewayManager::class);
    foreach (['http://checkout.stripe.com/x', 'https://checkout.stripe.com.evil.test/x', 'https://key@checkout.stripe.com/x'] as $url) {
        expect($g->redirectAllowed('stripe', $url))->toBeFalse();
    }
});

it('binds SSLCommerz refund status to the API-issued reference and original transaction', function () {
    $o = configuredGateway('sslcommerz');
    $admin = catalogAdmin();
    $a = GatewayAttempt::create(['uuid' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid(), 'order_id' => $o->id, 'driver' => 'sslcommerz', 'merchant_reference' => 'PAY-SSL-REFUND', 'amount' => 108000, 'currency' => 'BDT', 'verified_transaction' => 'SSL-ORIGINAL']);
    $r = GatewayRefund::create(['uuid' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid(), 'order_id' => $o->id, 'gateway_attempt_id' => $a->id, 'admin_id' => $admin->id, 'merchant_reference' => 'RF-SSL', 'amount' => 5000, 'currency' => 'BDT', 'reason' => 'Partial refund', 'restock_items' => []]);
    Http::fake(function ($q) use ($a) {
        return Http::response(isset($q['refund_ref_id']) ? ['APIConnect' => 'DONE', 'bank_tran_id' => 'SSL-ORIGINAL', 'tran_id' => $a->merchant_reference, 'refund_ref_id' => 'SSL-RF', 'status' => 'refunded'] : ['APIConnect' => 'DONE', 'bank_tran_id' => 'SSL-ORIGINAL', 'trans_id' => $a->merchant_reference, 'refund_ref_id' => 'SSL-RF', 'status' => 'success']);
    });
    $g = app(GatewayManager::class)->driver('sslcommerz');
    $accepted = $g->refund($r);
    expect($accepted->state)->toBe(RemoteState::Pending);
    $r->update(['provider_reference' => $accepted->reference]);
    $verified = $g->verifyRefund($r);
    expect($verified->state)->toBe(RemoteState::Paid)->and($verified->amount)->toBe(5000);
    Http::assertSent(fn ($q) => isset($q['refund_trans_id']) && $q['refund_trans_id'] === 'RF-SSL' && $q['refund_amount'] === '50.00');
});
it('checks the bKash refundable limit and original transaction for refund and status queries', function () {
    $o = configuredGateway('bkash');
    $admin = catalogAdmin();
    $a = GatewayAttempt::create(['uuid' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid(), 'order_id' => $o->id, 'driver' => 'bkash', 'merchant_reference' => 'PAY-BK-REFUND', 'provider_reference' => 'BK-ORIGINAL-PAY', 'amount' => 108000, 'currency' => 'BDT', 'verified_transaction' => 'BK-ORIGINAL-TRX']);
    $r = GatewayRefund::create(['uuid' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid(), 'order_id' => $o->id, 'gateway_attempt_id' => $a->id, 'admin_id' => $admin->id, 'merchant_reference' => 'RF-BK', 'amount' => 5000, 'currency' => 'BDT', 'reason' => 'Partial refund', 'restock_items' => []]);
    Http::fake(function ($q) use ($a) {
        if (str_contains($q->url(), 'grant-token')) {
            return Http::response(['id_token' => 'PRIVATE_TOKEN', 'refresh_token' => 'PRIVATE_REFRESH', 'expires_in' => 3600]);
        }if (str_ends_with($q->url(), '/query/payment')) {
            return Http::response(['paymentId' => 'BK-ORIGINAL-PAY', 'trxId' => 'BK-ORIGINAL-TRX', 'merchantInvoice' => $a->merchant_reference, 'maxRefundableAmount' => '1080.00']);
        }if (str_ends_with($q->url(), '/refund/payment/status')) {
            return Http::response(['originalTrxId' => 'BK-ORIGINAL-TRX', 'refundTransactions' => [['refundTrxId' => 'BK-RF', 'refundTransactionStatus' => 'Completed', 'refundAmount' => '50.00']]]);
        }

        return Http::response(['originalTrxId' => 'BK-ORIGINAL-TRX', 'sku' => 'RF-BK', 'refundTrxId' => 'BK-RF', 'refundTransactionStatus' => 'Completed', 'refundAmount' => '50.00', 'currency' => 'BDT']);
    });
    $g = app(GatewayManager::class)->driver('bkash');
    $result = $g->refund($r);
    expect($result->state)->toBe(RemoteState::Paid)->and($result->amount)->toBe(5000);
    $r->update(['provider_reference' => $result->reference]);
    expect($g->verifyRefund($r)->state)->toBe(RemoteState::Paid);
    Http::assertSent(fn ($q) => str_ends_with($q->url(), '/refund/payment/transaction') && $q['trxId'] === 'BK-ORIGINAL-TRX' && $q['refundAmount'] === '50.00');
});
it('rejects PayPal refund evidence without its original capture relationship', function () {
    $o = configuredGateway('paypal');
    $admin = catalogAdmin();
    $a = GatewayAttempt::create(['uuid' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid(), 'order_id' => $o->id, 'driver' => 'paypal', 'merchant_reference' => 'PAY-PP-REFUND', 'amount' => 108000, 'currency' => 'USD', 'verified_transaction' => 'PP-CAPTURE']);
    $r = GatewayRefund::create(['uuid' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid(), 'order_id' => $o->id, 'gateway_attempt_id' => $a->id, 'admin_id' => $admin->id, 'merchant_reference' => 'RF-PP', 'provider_reference' => 'PP-RF', 'amount' => 5000, 'currency' => 'USD', 'reason' => 'Partial refund', 'restock_items' => []]);
    $valid = false;
    Http::fake(function ($q) use (&$valid) {
        if (str_contains($q->url(), 'oauth2/token')) {
            return Http::response(['access_token' => 'PRIVATE_TOKEN', 'expires_in' => 3600]);
        }

        return Http::response(['id' => 'PP-RF', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '50.00'], 'links' => $valid ? [['rel' => 'up', 'href' => 'https://api-m.sandbox.paypal.com/v2/payments/captures/PP-CAPTURE']] : []]);
    });
    $g = app(GatewayManager::class)->driver('paypal');
    expect(fn () => $g->verifyRefund($r))->toThrow(IntegrationFailure::class);
    $valid = true;
    expect($g->verifyRefund($r)->state)->toBe(RemoteState::Paid);
});

it('recovers an unknown Stripe refund only after verifying its original payment metadata and exact amount', function () {
    $o = configuredGateway('stripe');
    Http::fake(function ($q) {
        $a = GatewayAttempt::firstOrFail();
        if (str_contains($q->url(), '/refunds')) {
            if ($q->method() === 'POST') {
                throw new ConnectionException('Refund result uncertain');
            }
            $r = GatewayRefund::firstOrFail();

            return Http::response(['id' => 're_recovered', 'payment_intent' => 'pi_recovery', 'amount' => 108000, 'currency' => 'bdt', 'status' => 'succeeded', 'metadata' => ['merchant_reference' => $r->merchant_reference]]);
        }

        return Http::response($q->method() === 'POST' ? ['id' => 'cs_recovery', 'url' => 'https://checkout.stripe.com/pay/test'] : ['id' => 'cs_recovery', 'livemode' => false, 'client_reference_id' => $a->merchant_reference, 'payment_intent' => 'pi_recovery', 'amount_total' => 108000, 'currency' => 'bdt', 'payment_status' => 'paid']);
    });
    $a = app(PaymentEngine::class)->start($o, 'stripe', (string) Str::uuid());
    app(PaymentEngine::class)->verify($a);
    $this->actingAs(catalogAdmin(), 'admin');
    app(ManageOrder::class)->transition($o, 'cancelled');
    $r = app(RefundEngine::class)->start($a->refresh(), 108000, 'Cancelled', (string) Str::uuid());
    expect($r->status)->toBe('unknown')->and($r->provider_reference)->toBeNull()->and($o->refresh()->refunded_amount)->toBe(0);
    app(RefundEngine::class)->attachReference($r, 're_recovered');
    expect($r->refresh()->status)->toBe('succeeded')->and($o->refresh()->refunded_amount)->toBe(108000)->and($o->refunds()->count())->toBe(1);
});
