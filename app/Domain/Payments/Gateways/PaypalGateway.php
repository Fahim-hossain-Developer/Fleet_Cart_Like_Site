<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Payments\Actions\ProviderMoney;
use App\Domain\Payments\Actions\ProviderTokens;
use App\Domain\Payments\DTOs\PaymentRedirect;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\RefundResult;
use App\Domain\Payments\DTOs\RemoteState;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Payments\Models\GatewayRefund;
use Illuminate\Http\Request;

final class PaypalGateway extends HttpGateway
{
    protected string $driver = 'paypal';

    private function base(): string
    {
        return $this->mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function token(): string
    {
        return app(ProviderTokens::class)->token($this->driver, $this->mode);
    }

    public function currencies(): array
    {
        return ['USD', 'EUR', 'GBP'];
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function initiate(GatewayAttempt $attempt, string $returnUrl, string $webhookUrl): PaymentRedirect
    {
        $token = $this->token();
        $d = $this->call(fn ($http) => $http->withToken($token)->withHeaders(['PayPal-Request-Id' => $attempt->merchant_reference])->post($this->base().'/v2/checkout/orders', ['intent' => 'CAPTURE', 'purchase_units' => [['reference_id' => $attempt->merchant_reference, 'custom_id' => $attempt->merchant_reference, 'invoice_id' => $attempt->merchant_reference, 'amount' => ['currency_code' => $attempt->currency, 'value' => ProviderMoney::decimal($attempt->amount)]]], 'payment_source' => ['paypal' => ['experience_context' => ['return_url' => $returnUrl, 'cancel_url' => $returnUrl, 'user_action' => 'PAY_NOW']]]]));
        $this->require(is_string($d['id'] ?? null));
        foreach ($d['links'] ?? [] as $link) {
            if (in_array($link['rel'] ?? '', ['approve', 'payer-action']) && is_string($link['href'] ?? null)) {
                return new PaymentRedirect($d['id'], $link['href']);
            }
        }throw new IntegrationFailure($this->driver, 'invalid_response');
    }

    public function verify(GatewayAttempt $attempt, bool $allowExecute = false): PaymentResult
    {
        if (! $attempt->provider_reference) {
            throw new IntegrationFailure($this->driver, 'reference_required');
        }$token = $this->token();
        $url = $this->base().'/v2/checkout/orders/'.rawurlencode($attempt->provider_reference);
        $d = $this->call(fn ($http) => $http->withToken($token)->get($url));
        $this->require(($d['id'] ?? null) === $attempt->provider_reference);
        if (($d['status'] ?? '') === 'APPROVED' && $allowExecute) {
            GatewayAttempt::whereKey($attempt->id)->update(['execution_started_at' => now()]);
            $this->call(fn ($http) => $http->withToken($token)->withHeaders(['Content-Type' => 'application/json', 'PayPal-Request-Id' => $attempt->merchant_reference])->send('POST', $url.'/capture', ['body' => '{}']));
            $d = $this->call(fn ($http) => $http->withToken($token)->get($url));
        }
        $this->require(count($d['purchase_units'] ?? []) === 1);
        $unit = $d['purchase_units'][0];
        $capture = $unit['payments']['captures'][0] ?? null;
        $paid = ($d['status'] ?? '') === 'COMPLETED' && is_array($capture) && ($capture['status'] ?? '') === 'COMPLETED';
        if ($paid) {
            $this->require(count($unit['payments']['captures']) === 1);
        }
        $state = $paid ? RemoteState::Paid : (($d['status'] ?? '') === 'VOIDED' ? RemoteState::Cancelled : RemoteState::Pending);
        $amount = $paid ? $capture['amount'] : $unit['amount'];

        return new PaymentResult($state, (string) ($unit['custom_id'] ?? $unit['invoice_id'] ?? ''), (string) ($capture['id'] ?? ''), ProviderMoney::minor($amount['value'] ?? ''), (string) ($amount['currency_code'] ?? ''), true);
    }

    public function refund(GatewayRefund $refund): RefundResult
    {
        $a = GatewayAttempt::findOrFail($refund->gateway_attempt_id);
        $this->require((bool) $a->verified_transaction);
        $token = $this->token();
        $d = $this->call(fn ($http) => $http->withToken($token)->withHeaders(['PayPal-Request-Id' => $refund->merchant_reference])->post($this->base().'/v2/payments/captures/'.rawurlencode($a->verified_transaction).'/refund', ['amount' => ['currency_code' => $refund->currency, 'value' => ProviderMoney::decimal($refund->amount)]]));

        return $this->refundResult($refund, $d);
    }

    public function verifyRefund(GatewayRefund $refund): RefundResult
    {
        if (! $refund->provider_reference) {
            throw new IntegrationFailure($this->driver, 'reference_required');
        }$token = $this->token();
        $d = $this->call(fn ($http) => $http->withToken($token)->get($this->base().'/v2/payments/refunds/'.rawurlencode($refund->provider_reference)));

        return $this->refundResult($refund, $d);
    }

    /**
     * @param array<string,mixed> $d */
    private function refundResult(GatewayRefund $refund, array $d): RefundResult
    {
        $this->require(is_string($d['id'] ?? null));
        $a = GatewayAttempt::findOrFail($refund->gateway_attempt_id);
        $parentVerified = false;
        foreach ($d['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'up') {
                $this->require(str_ends_with((string) ($link['href'] ?? ''), '/captures/'.$a->verified_transaction));
                $parentVerified = true;
            }
        }
        $this->require($parentVerified);
        $state = match ($d['status'] ?? '') {
            'COMPLETED' => RemoteState::Paid,'FAILED','CANCELLED' => RemoteState::Failed,default => RemoteState::Pending
        };

        return new RefundResult($state, $d['id'], ProviderMoney::minor($d['amount']['value'] ?? ''), (string) ($d['amount']['currency_code'] ?? ''));
    }

    public function authenticateWebhook(Request $request): bool
    {
        $cert = (string) $request->header('PAYPAL-CERT-URL');
        $parts = parse_url($cert);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || ! str_ends_with($host, '.paypal.com') || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            return false;
        }
        $headers = ['auth_algo' => 'PAYPAL-AUTH-ALGO', 'cert_url' => 'PAYPAL-CERT-URL', 'transmission_id' => 'PAYPAL-TRANSMISSION-ID', 'transmission_sig' => 'PAYPAL-TRANSMISSION-SIG', 'transmission_time' => 'PAYPAL-TRANSMISSION-TIME'];
        $body = [];
        foreach ($headers as $key => $name) {
            $body[$key] = (string) $request->header($name);
            if (! $body[$key]) {
                return false;
            }
        }
        try {
            $token = $this->token();
            $body['webhook_id'] = $this->credential('webhook_id');
            $body['webhook_event'] = $request->json()->all();
            $result = $this->call(fn ($http) => $http->withToken($token)->post($this->base().'/v1/notifications/verify-webhook-signature', $body));

            return ($result['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function webhook(Request $request): array
    {
        $d = $request->json()->all();
        $this->require(is_string($d['id'] ?? null) && is_string($d['event_type'] ?? null));
        $r = $d['resource'] ?? [];
        $reference = $r['supplementary_data']['related_ids']['order_id'] ?? $r['id'] ?? '';

        return ['id' => $d['id'], 'type' => $d['event_type'], 'reference' => (string) $reference];
    }
}
