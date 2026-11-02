<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Payments\Actions\ProviderMoney;
use App\Domain\Payments\Actions\ProviderTokens;
use App\Domain\Payments\DTOs\PaymentRedirect;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\RefundResult;
use App\Domain\Payments\DTOs\RemoteState;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Payments\Models\GatewayRefund;

final class BkashGateway extends HttpGateway
{
    protected string $driver = 'bkash';

    private function base(): string
    {
        return $this->mode === 'live' ? 'https://tokenized.pay.bka.sh/v2/tokenized-checkout' : 'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout';
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed> */
    private function post(string $path, array $body): array
    {
        $token = app(ProviderTokens::class)->token($this->driver, $this->mode);
        $key = $this->credential('app_key');

        return $this->call(fn ($http) => $http->withHeaders(['Authorization' => $token, 'X-App-Key' => $key])->post($this->base().$path, $body));
    }

    public function currencies(): array
    {
        return ['BDT'];
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function initiate(GatewayAttempt $attempt, string $returnUrl, string $webhookUrl): PaymentRedirect
    {
        $o = Order::findOrFail($attempt->order_id);
        $d = $this->post('/payment/create', ['payerReference' => $o->phone, 'callbackURL' => $returnUrl, 'amount' => ProviderMoney::decimal($attempt->amount), 'currency' => $attempt->currency, 'intent' => 'sale', 'merchantInvoiceNumber' => $attempt->merchant_reference]);
        $url = $d['bkashURL'] ?? $d['bKashURL'] ?? null;
        $this->require(is_string($d['paymentId'] ?? null) && is_string($url) && is_string($d['signature'] ?? null));
        $attempt->update(['callback_proof_hash' => hash('sha256', $d['signature'])]);

        return new PaymentRedirect($d['paymentId'], $url);
    }

    public function verify(GatewayAttempt $attempt, bool $allowExecute = false): PaymentResult
    {
        if (! $attempt->provider_reference) {
            throw new IntegrationFailure($this->driver, 'reference_required');
        }$d = $this->post('/query/payment', ['paymentId' => $attempt->provider_reference]);
        $this->require(($d['paymentId'] ?? '') === $attempt->provider_reference);
        // V2 permits exactly one execute attempt, including failures. Persist that claim before HTTP.
        if ($allowExecute && ($d['transactionStatus'] ?? '') === 'Initiated' && ($d['verificationStatus'] ?? '') === 'Complete' && GatewayAttempt::whereKey($attempt->id)->whereNull('execution_started_at')->update(['execution_started_at' => now()])) {
            $this->post('/payment/execute', ['paymentId' => $attempt->provider_reference]);
            $d = $this->post('/query/payment', ['paymentId' => $attempt->provider_reference]);
        }
        $this->require(($d['paymentId'] ?? '') === $attempt->provider_reference);

        return new PaymentResult(($d['transactionStatus'] ?? '') === 'Completed' ? RemoteState::Paid : RemoteState::Pending, (string) ($d['merchantInvoice'] ?? ''), (string) ($d['trxId'] ?? ''), ProviderMoney::minor($d['amount'] ?? '0'), (string) ($d['currency'] ?? 'BDT'), true);
    }

    public function refund(GatewayRefund $refund): RefundResult
    {
        $a = GatewayAttempt::findOrFail($refund->gateway_attempt_id);
        $this->require((bool) $a->provider_reference && (bool) $a->verified_transaction);
        $payment = $this->post('/query/payment', ['paymentId' => $a->provider_reference]);
        $this->require(($payment['paymentId'] ?? '') === $a->provider_reference && ($payment['trxId'] ?? '') === $a->verified_transaction && ($payment['merchantInvoice'] ?? '') === $a->merchant_reference);
        if ($refund->amount > ProviderMoney::minor($payment['maxRefundableAmount'] ?? '0') || $a->created_at->lt(now()->subDays(60))) {
            throw new IntegrationFailure($this->driver, 'refund_limit');
        }
        $d = $this->post('/refund/payment/transaction', ['paymentId' => $a->provider_reference, 'refundAmount' => ProviderMoney::decimal($refund->amount), 'sku' => $refund->merchant_reference, 'reason' => mb_substr($refund->reason, 0, 255), 'trxId' => $a->verified_transaction]);
        $this->require(($d['originalTrxId'] ?? '') === $a->verified_transaction && ($d['sku'] ?? '') === $refund->merchant_reference && is_string($d['refundTrxId'] ?? null));

        return new RefundResult(($d['refundTransactionStatus'] ?? '') === 'Completed' ? RemoteState::Paid : RemoteState::Pending, $d['refundTrxId'], ProviderMoney::minor($d['refundAmount'] ?? '0'), (string) ($d['currency'] ?? ''));
    }

    public function verifyRefund(GatewayRefund $refund): RefundResult
    {
        if (! $refund->provider_reference) {
            throw new IntegrationFailure($this->driver, 'reference_required');
        }$a = GatewayAttempt::findOrFail($refund->gateway_attempt_id);
        $d = $this->post('/refund/payment/status', ['paymentId' => $a->provider_reference, 'trxId' => $a->verified_transaction]);
        $this->require(($d['originalTrxId'] ?? '') === $a->verified_transaction);
        foreach ($d['refundTransactions'] ?? [] as $row) {
            if (($row['refundTrxId'] ?? '') === $refund->provider_reference) {
                return new RefundResult(($row['refundTransactionStatus'] ?? '') === 'Completed' ? RemoteState::Paid : RemoteState::Pending, $refund->provider_reference, ProviderMoney::minor($row['refundAmount'] ?? '0'), 'BDT');
            }
        }
        throw new IntegrationFailure($this->driver, 'refund_not_confirmed');
    }
    // TODO(api-docs): standalone bKash IPN authentication is not specified by the retrieved Tokenized V2 pages.
}
