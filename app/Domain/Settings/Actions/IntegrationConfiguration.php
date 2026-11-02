<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Orders\Actions\OrderAccess;
use App\Domain\Settings\Models\IntegrationConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IntegrationConfiguration
{
    public const PROVIDERS = [
        'fake' => ['kind' => 'payment', 'implemented' => true, 'keys' => [], 'docs' => ''],
        'sslcommerz' => ['kind' => 'payment', 'implemented' => true, 'keys' => ['store_id', 'store_password'], 'docs' => 'https://developer.sslcommerz.com/doc/v4/'],
        'bkash' => ['kind' => 'payment', 'implemented' => true, 'keys' => ['username', 'password', 'app_key', 'app_secret'], 'docs' => 'https://developer.bka.sh/docs/product-overview'],
        'aamarpay' => ['kind' => 'payment', 'implemented' => true, 'keys' => ['store_id', 'signature_key'], 'docs' => 'https://aamarpay.readme.io/reference/overview'],
        'stripe' => ['kind' => 'payment', 'implemented' => true, 'keys' => ['api_key', 'webhook_secret'], 'docs' => 'https://docs.stripe.com/api/checkout/sessions'],
        'paypal' => ['kind' => 'payment', 'implemented' => true, 'keys' => ['client_id', 'client_secret', 'webhook_id'], 'docs' => 'https://developer.paypal.com/docs/api/orders/v2/'],
        'nagad' => ['kind' => 'payment', 'implemented' => false, 'keys' => ['private_configuration_json'], 'docs' => 'https://developer.nagad.com.bd'],
        'courier_fake' => ['kind' => 'courier', 'implemented' => true, 'keys' => [], 'docs' => ''],
        'courier_manual' => ['kind' => 'courier', 'implemented' => true, 'keys' => [], 'docs' => ''],
        'steadfast' => ['kind' => 'courier', 'implemented' => false, 'keys' => ['private_configuration_json'], 'docs' => 'https://steadfast.com.bd/api/v1/documentation'],
        'pathao' => ['kind' => 'courier', 'implemented' => false, 'keys' => ['private_configuration_json'], 'docs' => 'https://merchant.pathao.com/courier/developer-api'],
        'redx' => ['kind' => 'courier', 'implemented' => false, 'keys' => ['private_configuration_json'], 'docs' => 'https://redx.com.bd'],
        'sms_log' => ['kind' => 'sms', 'implemented' => true, 'keys' => [], 'docs' => ''],
        'sms_twilio' => ['kind' => 'sms', 'implemented' => true, 'keys' => ['account_sid', 'auth_token', 'from'], 'docs' => 'https://www.twilio.com/docs/messaging/api/message-resource'],
        'sms_custom' => ['kind' => 'sms', 'implemented' => true, 'keys' => ['api_key'], 'docs' => ''],
    ];

    public function connection(string $driver): IntegrationConnection
    {
        abort_unless(isset(self::PROVIDERS[$driver]), 404);
        $defaultEnabled = in_array($driver, ['fake', 'courier_fake', 'courier_manual', 'sms_log'], true) && app()->environment('local', 'testing');

        return IntegrationConnection::firstOrNew(['driver' => $driver], ['kind' => self::PROVIDERS[$driver]['kind'], 'is_enabled' => $defaultEnabled, 'mode' => 'sandbox', 'config' => ['documentation_url' => self::PROVIDERS[$driver]['docs'], 'minimum' => 1, 'maximum' => 100000000000]]);
    }

    public function credential(string $driver, string $key, ?string $mode = null): ?string
    {
        $mode ??= $this->connection($driver)->mode;

        return app(CredentialVault::class)->get('integration.'.$driver.'.'.$mode.'.'.$key);
    }

    public function secretKey(string $driver, string $key): string
    {
        return 'integration.'.$driver.'.'.$this->connection($driver)->mode.'.'.$key;
    }

    public function ready(string $driver): bool
    {
        if (! isset(self::PROVIDERS[$driver]) || ! self::PROVIDERS[$driver]['implemented']) {
            return false;
        }
        $c = $this->connection($driver);
        if (! $c->is_enabled) {
            return false;
        }
        if (app()->environment('production') && $c->mode !== 'live') {
            return false;
        }
        if (in_array($driver, ['fake', 'courier_fake', 'sms_log'], true) && ! app()->environment('local', 'testing')) {
            return false;
        }
        foreach (self::PROVIDERS[$driver]['keys'] as $key) {
            if (! $this->credential($driver, $key)) {
                return false;
            }
        }
        if ($driver === 'sms_custom' && empty($c->config['endpoint'])) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $credentials */
    public function save(string $driver, array $data, array $credentials): void
    {
        app(OrderAccess::class)->staff('integrations.manage');
        abort_unless(isset(self::PROVIDERS[$driver]), 404);
        $d = validator($data, ['is_enabled' => 'required|boolean', 'mode' => 'required|in:sandbox,live', 'documentation_url' => 'nullable|url:https|max:2048', 'minimum' => 'required|integer|min:1|max:100000000000', 'maximum' => 'required|integer|min:1|max:100000000000|gte:minimum', 'endpoint' => 'nullable|url:https|max:2048', 'format' => 'nullable|in:json,form', 'recipient_field' => 'nullable|regex:/^[a-zA-Z_][a-zA-Z0-9_]{0,31}$/D', 'message_field' => 'nullable|regex:/^[a-zA-Z_][a-zA-Z0-9_]{0,31}$/D', 'sender' => 'nullable|string|max:32'])->validate();
        if (in_array($driver, ['fake', 'courier_fake', 'sms_log'], true) && $d['mode'] !== 'sandbox') {
            throw ValidationException::withMessages(['mode' => __('integrations.local_mode_only')]);
        }
        if ($d['is_enabled'] && ! self::PROVIDERS[$driver]['implemented']) {
            throw ValidationException::withMessages(['is_enabled' => __('integrations.protocol_pending')]);
        }
        if ($driver === 'sms_custom' && ! empty($d['endpoint'])) {
            $parts = parse_url($d['endpoint']);
            $host = strtolower($parts['host'] ?? '');
            if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['port']) || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal') || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
                throw ValidationException::withMessages(['endpoint' => __('integrations.public_endpoint_required')]);
            }
        }
        $private = [];
        foreach (self::PROVIDERS[$driver]['keys'] as $key) {
            $value = $credentials[$key] ?? null;
            if (is_string($value) && $value !== '') {
                validator(['value' => $value], ['value' => 'string|max:32000'])->validate();
                if ($key === 'private_configuration_json') {
                    validator(['value' => $value], ['value' => 'json'])->validate();
                }$private[$key] = $value;
            }
        }
        DB::transaction(function () use ($driver, $d, $private) {
            $c = $this->connection($driver);
            $c->fill(['is_enabled' => (bool) $d['is_enabled'], 'mode' => $d['mode'], 'config' => array_diff_key($d, array_flip(['is_enabled', 'mode']))]);
            $c->save();
            foreach ($private as $key => $value) {
                app(CredentialVault::class)->replace($this->secretKey($driver, $key), $value);
            }
        });
    }
}
