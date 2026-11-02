<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Payments\Actions\ProviderMoney;
use App\Domain\Payments\DTOs\PaymentRedirect;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\RemoteState;
use App\Domain\Payments\Models\GatewayAttempt;

final class AamarPayGateway extends HttpGateway
{
    protected string $driver = 'aamarpay';

    private function base(): string
    {
        return $this->mode === 'live' ? 'https://secure.aamarpay.com' : 'https://sandbox.aamarpay.com';
    }

    public function currencies(): array
    {
        return ['BDT'];
    }

    public function initiate(GatewayAttempt $attempt, string $returnUrl, string $webhookUrl): PaymentRedirect
    {
        $o = Order::findOrFail($attempt->order_id);
        if (! $o->guest_email) {
            throw new IntegrationFailure($this->driver, 'email_required');
        }$a = $o->shipping_address;
        $d = $this->call(fn ($http) => $http->post($this->base().'/jsonpost.php', ['store_id' => $this->credential('store_id'), 'signature_key' => $this->credential('signature_key'), 'tran_id' => $attempt->merchant_reference, 'amount' => ProviderMoney::decimal($attempt->amount), 'currency' => $attempt->currency, 'success_url' => $returnUrl, 'fail_url' => $returnUrl, 'cancel_url' => $returnUrl, 'desc' => 'Order '.$attempt->merchant_reference, 'cus_name' => $a['name'], 'cus_email' => $o->guest_email, 'cus_phone' => $o->phone, 'cus_add1' => $a['line1'], 'cus_country' => 'Bangladesh', 'type' => 'json']));
        $this->require(in_array($d['result'] ?? false, ['true', true], true) && is_string($d['payment_url'] ?? null));

        return new PaymentRedirect($attempt->merchant_reference, $d['payment_url']);
    }

    public function verify(GatewayAttempt $attempt, bool $allowExecute = false): PaymentResult
    {
        $d = $this->call(fn ($http) => $http->get($this->base().'/api/v1/trxcheck/request.php', ['request_id' => $attempt->merchant_reference, 'store_id' => $this->credential('store_id'), 'signature_key' => $this->credential('signature_key'), 'type' => 'json']));
        $this->require(($d['store_id'] ?? '') === $this->credential('store_id'));
        $state = match ((string) ($d['status_code'] ?? '')) {
            '2' => RemoteState::Paid,default => RemoteState::Pending
        };

        return new PaymentResult($state, (string) ($d['mer_txnid'] ?? ''), (string) ($d['pg_txnid'] ?? ''), ProviderMoney::minor($d['amount_currency'] ?? $d['amount'] ?? '0'), (string) ($d['currency_merchant'] ?? $d['currency'] ?? ''), true);
    }
    // TODO(api-docs): published IPN page promises a signature but does not define its header/canonicalization.
    // Original-gateway refunds have no protocol in the retrieved reference; use the audited manual workflow.
}
