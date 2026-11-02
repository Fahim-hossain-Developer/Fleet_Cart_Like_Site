<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Payments\DTOs\PaymentRedirect;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\RefundResult;
use App\Domain\Payments\DTOs\RemoteState;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Payments\Models\GatewayRefund;
use Illuminate\Http\Request;

final class StripeGateway extends HttpGateway
{
    protected string $driver = 'stripe';

    public function currencies(): array
    {
        return ['BDT', 'USD', 'EUR', 'GBP'];
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function initiate(GatewayAttempt $attempt, string $returnUrl, string $webhookUrl): PaymentRedirect
    {
        $key = $this->credential('api_key');
        $data = $this->call(fn ($http) => $http->withToken($key)->withHeaders(['Idempotency-Key' => $attempt->merchant_reference])->asForm()->post('https://api.stripe.com/v1/checkout/sessions', ['mode' => 'payment', 'client_reference_id' => $attempt->merchant_reference, 'success_url' => $returnUrl, 'cancel_url' => $returnUrl, 'metadata' => ['merchant_reference' => $attempt->merchant_reference], 'payment_intent_data' => ['metadata' => ['merchant_reference' => $attempt->merchant_reference]], 'line_items' => [['quantity' => 1, 'price_data' => ['currency' => strtolower($attempt->currency), 'unit_amount' => $attempt->amount, 'product_data' => ['name' => 'Order '.$attempt->merchant_reference]]]]]));
        $this->require(is_string($data['id'] ?? null) && is_string($data['url'] ?? null));

        return new PaymentRedirect($data['id'], $data['url']);
    }

    public function verify(GatewayAttempt $attempt, bool $allowExecute = false): PaymentResult
    {
        if (! $attempt->provider_reference) {
            throw new IntegrationFailure($this->driver, 'reference_required');
        }$key = $this->credential('api_key');
        $d = $this->call(fn ($http) => $http->withToken($key)->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($attempt->provider_reference)));
        $this->require(($d['id'] ?? null) === $attempt->provider_reference && isset($d['livemode']) && is_bool($d['livemode']));
        $this->require(is_int($d['amount_total'] ?? null) && $d['amount_total'] >= 0 && $d['amount_total'] <= 100000000000);
        $state = ($d['payment_status'] ?? '') === 'paid' ? RemoteState::Paid : (($d['status'] ?? '') === 'expired' ? RemoteState::Failed : RemoteState::Pending);

        return new PaymentResult($state, (string) ($d['client_reference_id'] ?? ''), (string) ($d['payment_intent'] ?? ''), (int) ($d['amount_total'] ?? 0), strtoupper((string) ($d['currency'] ?? '')), $d['livemode'] === ($this->mode === 'live'));
    }

    public function refund(GatewayRefund $refund): RefundResult
    {
        $attempt = GatewayAttempt::findOrFail($refund->gateway_attempt_id);
        $key = $this->credential('api_key');
        $data = $this->call(fn ($http) => $http->withToken($key)->withHeaders(['Idempotency-Key' => $refund->merchant_reference])->asForm()->post('https://api.stripe.com/v1/refunds', ['payment_intent' => $attempt->verified_transaction, 'amount' => $refund->amount, 'reason' => 'requested_by_customer', 'metadata' => ['merchant_reference' => $refund->merchant_reference]]));

        return $this->refundResult($refund, $data);
    }

    public function verifyRefund(GatewayRefund $refund): RefundResult
    {
        if (! $refund->provider_reference) {
            throw new IntegrationFailure($this->driver, 'reference_required');
        }$key = $this->credential('api_key');
        $data = $this->call(fn ($http) => $http->withToken($key)->get('https://api.stripe.com/v1/refunds/'.rawurlencode($refund->provider_reference)));

        return $this->refundResult($refund, $data);
    }

    /**
     * @param array<string,mixed> $data */
    private function refundResult(GatewayRefund $refund, array $data): RefundResult
    {
        $a = GatewayAttempt::findOrFail($refund->gateway_attempt_id);
        $this->require(is_string($data['id'] ?? null) && ($data['payment_intent'] ?? null) === $a->verified_transaction && ($data['metadata']['merchant_reference'] ?? null) === $refund->merchant_reference);
        $this->require(is_int($data['amount'] ?? null) && $data['amount'] >= 0 && $data['amount'] <= 100000000000);
        $state = match ($data['status'] ?? '') {
            'succeeded' => RemoteState::Paid,'failed','canceled' => RemoteState::Failed,default => RemoteState::Pending
        };

        return new RefundResult($state, $data['id'], (int) ($data['amount'] ?? 0), strtoupper((string) ($data['currency'] ?? '')));
    }

    public function authenticateWebhook(Request $request): bool
    {
        try {
            $secret = $this->credential('webhook_secret');
        } catch (IntegrationFailure $e) {
            return false;
        }$timestamp = null;
        $signatures = [];
        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) !== 2) {
                continue;
            }if ($pair[0] === 't' && ctype_digit($pair[1])) {
                $timestamp = (int) $pair[1];
            }if ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }
        if (! $timestamp || abs(now()->timestamp - $timestamp) > 300) {
            return false;
        }$expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function webhook(Request $request): array
    {
        $d = $request->json()->all();
        $this->require(is_string($d['id'] ?? null) && is_string($d['type'] ?? null));
        $obj = $d['data']['object'] ?? [];
        $reference = $obj['client_reference_id'] ?? $obj['metadata']['merchant_reference'] ?? $obj['id'] ?? '';

        return ['id' => $d['id'], 'type' => $d['type'], 'reference' => (string) $reference];
    }
}
