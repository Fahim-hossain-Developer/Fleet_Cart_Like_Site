<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Payments\Actions\ProviderMoney;
use App\Domain\Payments\DTOs\PaymentRedirect;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\RefundResult;
use App\Domain\Payments\DTOs\RemoteState;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Payments\Models\GatewayRefund;
use Illuminate\Http\Request;

final class SslCommerzGateway extends HttpGateway
{
    protected string $driver = 'sslcommerz';

    private function base(): string
    {
        return $this->mode === 'live' ? 'https://securepay.sslcommerz.com' : 'https://sandbox.sslcommerz.com';
    }

    /**
     * @return array<string,string> */
    private function auth(): array
    {
        return ['store_id' => $this->credential('store_id'), 'store_passwd' => $this->credential('store_password'), 'format' => 'json'];
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
        if (! $o->guest_email) {
            throw new IntegrationFailure($this->driver, 'email_required');
        }if ($attempt->amount < 1000 || $attempt->amount > 50000000) {
            throw new IntegrationFailure($this->driver, 'amount_limit');
        }if (strlen($returnUrl) > 255 || strlen($webhookUrl) > 255) {
            throw new IntegrationFailure($this->driver, 'callback_url_too_long');
        }
        $a = $o->shipping_address;
        $body = $this->auth() + ['total_amount' => ProviderMoney::decimal($attempt->amount), 'currency' => $attempt->currency, 'tran_id' => $attempt->merchant_reference, 'success_url' => $returnUrl, 'fail_url' => $returnUrl, 'cancel_url' => $returnUrl, 'ipn_url' => $webhookUrl, 'cus_name' => mb_substr($a['name'], 0, 50), 'cus_email' => $o->guest_email, 'cus_phone' => $o->phone, 'cus_add1' => mb_substr($a['line1'], 0, 50), 'cus_city' => $a['district']['en'] ?? '', 'cus_country' => 'Bangladesh', 'shipping_method' => 'NO', 'product_name' => 'Order '.$attempt->merchant_reference, 'product_category' => 'general', 'product_profile' => 'general', 'num_of_item' => $o->items()->sum('qty'), 'value_a' => $attempt->uuid];
        $d = $this->call(fn ($http) => $http->asForm()->post($this->base().'/gwprocess/v4/api.php', $body));
        $this->require(($d['status'] ?? '') === 'SUCCESS' && is_string($d['sessionkey'] ?? null) && is_string($d['GatewayPageURL'] ?? null));

        return new PaymentRedirect($d['sessionkey'], $d['GatewayPageURL']);
    }

    public function verify(GatewayAttempt $attempt, bool $allowExecute = false): PaymentResult
    {
        $params = $this->auth() + ($attempt->provider_reference ? ['sessionkey' => $attempt->provider_reference] : ['tran_id' => $attempt->merchant_reference]);
        $d = $this->call(fn ($http) => $http->get($this->base().'/validator/api/merchantTransIDvalidationAPI.php', $params));
        if (isset($d['element'])) {
            $found = null;
            foreach ($d['element'] as $row) {
                if (($row['tran_id'] ?? '') === $attempt->merchant_reference && in_array($row['status'] ?? '', ['VALID', 'VALIDATED'], true)) {
                    $found = $row;
                    break;
                }
            }if (! $found) {
                throw new IntegrationFailure($this->driver, 'transaction_not_confirmed');
            }$d = $found;
        }
        if (in_array($d['status'] ?? '', ['VALID', 'VALIDATED'], true)) {
            $this->require(is_string($d['val_id'] ?? null));
            $params = $this->auth() + ['val_id' => $d['val_id']];
            $d = $this->call(fn ($http) => $http->get($this->base().'/validator/api/validationserverAPI.php', $params));
        }
        $state = match ($d['status'] ?? '') {
            'VALID','VALIDATED' => RemoteState::Paid,'FAILED' => RemoteState::Failed,'CANCELLED' => RemoteState::Cancelled,default => RemoteState::Pending
        };

        return new PaymentResult($state, (string) ($d['tran_id'] ?? ''), (string) ($d['bank_tran_id'] ?? ''), ProviderMoney::minor($d['currency_amount'] ?? $d['amount'] ?? '0'), strtoupper((string) ($d['currency_type'] ?? $d['currency'] ?? $attempt->currency)), true, (int) ($d['risk_level'] ?? 0) === 1);
    }

    public function refund(GatewayRefund $refund): RefundResult
    {
        $a = GatewayAttempt::findOrFail($refund->gateway_attempt_id);
        $params = $this->auth() + ['bank_tran_id' => $a->verified_transaction, 'refund_trans_id' => $refund->merchant_reference, 'refund_amount' => ProviderMoney::decimal($refund->amount), 'refund_remarks' => mb_substr($refund->reason, 0, 255), 'format' => 'json'];
        $d = $this->call(fn ($http) => $http->get($this->base().'/validator/api/merchantTransIDvalidationAPI.php', $params));
        $this->require(($d['APIConnect'] ?? '') === 'DONE' && ($d['bank_tran_id'] ?? '') === $a->verified_transaction && ($d['trans_id'] ?? '') === $a->merchant_reference);
        if (($d['status'] ?? '') === 'failed') {
            return new RefundResult(RemoteState::Failed, '', $refund->amount, $refund->currency);
        }
        $this->require(is_string($d['refund_ref_id'] ?? null));

        return new RefundResult(RemoteState::Pending, $d['refund_ref_id'], $refund->amount, $refund->currency);
    }

    public function verifyRefund(GatewayRefund $refund): RefundResult
    {
        if (! $refund->provider_reference) {
            throw new IntegrationFailure($this->driver, 'reference_required');
        }$a = GatewayAttempt::findOrFail($refund->gateway_attempt_id);
        $params = $this->auth() + ['refund_ref_id' => $refund->provider_reference];
        $d = $this->call(fn ($http) => $http->get($this->base().'/validator/api/merchantTransIDvalidationAPI.php', $params));
        $this->require(($d['APIConnect'] ?? '') === 'DONE' && ($d['bank_tran_id'] ?? '') === $a->verified_transaction && ($d['tran_id'] ?? '') === $a->merchant_reference && ($d['refund_ref_id'] ?? '') === $refund->provider_reference);
        // The query omits amount. Its API-created reference is bound to our immutable accepted refund request.
        $state = match ($d['status'] ?? '') {
            'refunded' => RemoteState::Paid,'cancelled' => RemoteState::Failed,default => RemoteState::Pending
        };

        return new RefundResult($state, $refund->provider_reference, $refund->amount, $refund->currency);
    }

    public function authenticateWebhook(Request $request): bool
    {
        $data = $request->request->all();
        if (! is_string($data['verify_key'] ?? null) || ! is_string($data['verify_sign'] ?? null)) {
            return false;
        }
        $keys = explode(',', $data['verify_key']);
        foreach (['tran_id', 'val_id', 'amount', 'currency'] as $required) {
            if (! in_array($required, $keys, true)) {
                return false;
            }
        }
        try {
            $signed = ['store_passwd' => md5($this->credential('store_password'))];
        } catch (IntegrationFailure $e) {
            return false;
        }
        foreach ($keys as $key) {
            if (! preg_match('/^[a-zA-Z0-9_]+$/D', $key) || ! array_key_exists($key, $data) || ! is_scalar($data[$key]) || $key === 'store_passwd') {
                return false;
            }$signed[$key] = (string) $data[$key];
        }
        ksort($signed);
        $parts = [];
        foreach ($signed as $key => $value) {
            $parts[] = $key.'='.$value;
        }

        return hash_equals(md5(implode('&', $parts)), $data['verify_sign']);
    }

    public function webhook(Request $request): array
    {
        $d = $request->request->all();
        $this->require(is_string($d['val_id'] ?? null) && is_string($d['tran_id'] ?? null));

        return ['id' => $d['val_id'].':'.(string) ($d['status'] ?? ''), 'type' => 'payment.'.(string) ($d['status'] ?? ''), 'reference' => $d['tran_id']];
    }
}
