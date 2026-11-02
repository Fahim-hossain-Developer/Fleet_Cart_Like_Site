<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Settings\Actions\CredentialVault;
use App\Domain\Settings\Actions\IntegrationConfiguration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

final class ProviderTokens
{
    public function token(string $driver, string $mode): string
    {
        return Cache::lock('integration-token:'.$driver.':'.$mode, 90)->block(10, function () use ($driver, $mode) {
            $config = app(IntegrationConfiguration::class);
            $credentials = [];
            foreach ($driver === 'bkash' ? ['username', 'password', 'app_key', 'app_secret'] : ['client_id', 'client_secret'] as $key) {
                $credentials[$key] = $config->credential($driver, $key, $mode);
                if (! $credentials[$key]) {
                    throw new IntegrationFailure($driver, 'configuration_missing');
                }
            }
            $fingerprint = hash('sha256', json_encode($credentials, JSON_THROW_ON_ERROR));
            $vaultKey = 'integration.'.$driver.'.'.$mode.'.token_bundle';
            $raw = app(CredentialVault::class)->get($vaultKey);
            $bundle = $raw ? json_decode($raw, true) : null;
            if (is_array($bundle) && ($bundle['fingerprint'] ?? '') === $fingerprint && ($bundle['expires_at'] ?? 0) > now()->timestamp && is_string($bundle['token'] ?? null)) {
                return $bundle['token'];
            }
            if ($driver === 'paypal') {
                $base = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
                $data = app(ProviderHttp::class)->json($driver, fn ($http) => $http->asForm()->withBasicAuth($credentials['client_id'], $credentials['client_secret'])->post($base.'/v1/oauth2/token', ['grant_type' => 'client_credentials']));
                $token = $data['access_token'] ?? null;
                $refresh = null;
            } else {
                $base = $mode === 'live' ? 'https://tokenized.pay.bka.sh/v2/tokenized-checkout' : 'https://tokenized.sandbox.bka.sh/v2/tokenized-checkout';
                $refresh = is_array($bundle) && ($bundle['fingerprint'] ?? '') === $fingerprint ? ($bundle['refresh'] ?? null) : null;
                $operation = $refresh ? 'refresh-token' : 'grant-token';
                if (! $refresh) {
                    $limit = 'bkash-grant:'.$mode.':'.$fingerprint;
                    if (RateLimiter::tooManyAttempts($limit, 2)) {
                        throw new IntegrationFailure($driver, 'token_rate_limited');
                    }RateLimiter::hit($limit, 3600);
                }
                $body = ['app_key' => $credentials['app_key'], 'app_secret' => $credentials['app_secret']];
                if ($refresh) {
                    $body['refresh_token'] = $refresh;
                }
                $data = app(ProviderHttp::class)->json($driver, fn ($http) => $http->withHeaders(['username' => $credentials['username'], 'password' => $credentials['password']])->post($base.'/auth/'.$operation, $body));
                $token = $data['id_token'] ?? null;
                $refresh = $data['refresh_token'] ?? null;
            }
            if (! is_string($token) || ! $token || ! isset($data['expires_in']) || (int) $data['expires_in'] < 1) {
                throw new IntegrationFailure($driver, 'token_rejected');
            }
            app(CredentialVault::class)->replace($vaultKey, json_encode(['token' => $token, 'refresh' => $refresh, 'fingerprint' => $fingerprint, 'expires_at' => now()->timestamp + max(1, min(86400, (int) $data['expires_in']) - 120)], JSON_THROW_ON_ERROR));

            return $token;
        });
    }
}
