<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Models\SocialIdentity;
use App\Domain\Payments\Actions\ProviderHttp;
use App\Domain\Settings\Actions\CredentialVault;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SocialLogin
{
    public function ready(string $provider): bool
    {
        return in_array($provider, ['google', 'facebook'], true) && store_setting('social_login_'.$provider, false) && filled(app(CredentialVault::class)->get('social.'.$provider.'.client_id')) && filled(app(CredentialVault::class)->get('social.'.$provider.'.client_secret'));
    }

    public function begin(string $provider, bool $link): string
    {
        abort_unless($this->ready($provider), 404);
        $user = auth('web')->user();
        // Only fresh contact proof can attach another login method to an existing account.
        if ($link) {
            abort_unless($user && ! $user->is_blocked && max((int) session('customer_email_verified_until', 0), (int) session('customer_phone_verified_until', 0)) > now()->timestamp, 403);
        } else {
            abort_if($user !== null, 422);
        }
        $state = Str::random(64);
        $verifier = Str::random(96);
        session(['social_login' => ['state_hash' => hash('sha256', $state), 'provider' => $provider, 'verifier' => $verifier, 'expires' => now()->addMinutes(10)->timestamp, 'user_id' => $link ? $user->id : null, 'auth_version' => $link ? $user->auth_version : null]]);
        $client = app(CredentialVault::class)->get('social.'.$provider.'.client_id');
        $redirect = route('social.callback', $provider);
        $params = ['client_id' => $client, 'redirect_uri' => $redirect, 'response_type' => 'code', 'state' => $state, 'scope' => $provider === 'google' ? 'openid email profile' : 'public_profile,email'];
        if ($provider === 'google') {
            $params += ['code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256', 'prompt' => 'select_account'];
        }

        return ($provider === 'google' ? 'https://accounts.google.com/o/oauth2/v2/auth' : 'https://www.facebook.com/v26.0/dialog/oauth').'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function callback(string $provider, string $state, string $code): User
    {
        $pending = session()->pull('social_login');
        abort_unless($this->ready($provider) && is_array($pending) && ($pending['provider'] ?? null) === $provider && ($pending['expires'] ?? 0) > now()->timestamp && hash_equals($pending['state_hash'] ?? '', hash('sha256', $state)) && $code !== '' && strlen($code) <= 4096, 419);
        $current = auth('web')->user();
        $linkId = $pending['user_id'] ?? null;
        if ($linkId) {
            abort_unless($current && $current->id === $linkId && $current->auth_version === $pending['auth_version'] && ! $current->is_blocked && max((int) session('customer_email_verified_until', 0), (int) session('customer_phone_verified_until', 0)) > now()->timestamp, 403);
        } else {
            abort_if($current !== null, 403);
        }
        $vault = app(CredentialVault::class);
        $client = $vault->get('social.'.$provider.'.client_id');
        $secret = $vault->get('social.'.$provider.'.client_secret');
        $http = app(ProviderHttp::class);
        $parameters = ['client_id' => $client, 'client_secret' => $secret, 'redirect_uri' => route('social.callback', $provider), 'code' => $code];
        if ($provider === 'google') {
            $parameters += ['grant_type' => 'authorization_code', 'code_verifier' => $pending['verifier']];
        }
        $token = $http->json('social_'.$provider, fn ($r) => $r->asForm()->post($provider === 'google' ? 'https://oauth2.googleapis.com/token' : 'https://graph.facebook.com/v26.0/oauth/access_token', $parameters));
        $access = $token['access_token'] ?? null;
        abort_unless(is_string($access) && strlen($access) <= 4096, 422);
        if ($provider === 'facebook') {
            $proof = $http->json('social_facebook', fn ($r) => $r->withToken($client.'|'.$secret)->get('https://graph.facebook.com/v26.0/debug_token', ['input_token' => $access]));
            abort_unless(($proof['data']['is_valid'] ?? false) === true && (string) ($proof['data']['app_id'] ?? '') === $client && (int) ($proof['data']['expires_at'] ?? 0) > now()->timestamp, 422);
            $profile = $http->json('social_facebook', fn ($r) => $r->withToken($access)->get('https://graph.facebook.com/v26.0/me', ['fields' => 'id,name,email', 'appsecret_proof' => hash_hmac('sha256', $access, $secret)]));
            abort_unless((string) ($profile['id'] ?? '') === (string) ($proof['data']['user_id'] ?? ''), 422);
        } else {
            $profile = $http->json('social_google', fn ($r) => $r->withToken($access)->get('https://openidconnect.googleapis.com/v1/userinfo'));
        }
        $subject = $profile[$provider === 'google' ? 'sub' : 'id'] ?? null;
        abort_unless(is_string($subject) && preg_match('/^[a-zA-Z0-9_-]{1,255}$/D', $subject), 422);
        $email = $provider === 'google' && ($profile['email_verified'] ?? false) === true && filter_var($profile['email'] ?? null, FILTER_VALIDATE_EMAIL) ? strtolower($profile['email']) : null;

        // No provider tokens are retained; userinfo is fetched directly over TLS using this code's token.
        return DB::transaction(function () use ($provider, $subject, $profile, $email, $linkId) {
            $identity = SocialIdentity::where('provider', $provider)->where('subject_hash', hash('sha256', $subject))->lockForUpdate()->first();
            if ($identity) {
                abort_if($linkId && $identity->user_id !== $linkId, 409);
                $u = User::lockForUpdate()->findOrFail($identity->user_id);
                abort_if($u->is_blocked, 403);

                return $u;
            }
            if ($linkId) {
                $u = User::lockForUpdate()->findOrFail($linkId);
                abort_if($u->is_blocked, 403);
            } else {
                if ($email && User::where('email', $email)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['social' => __('marketing.social_existing')]);
                }
                $name = mb_substr(trim(strip_tags((string) ($profile['name'] ?? ''))), 0, 100) ?: __('orders.account');
                $u = new User(['name' => $name, 'email' => $email, 'password' => Str::random(64)]);
                $u->forceFill(['email_verified_at' => $email ? now() : null])->save();
            }
            abort_if(SocialIdentity::where('user_id', $u->id)->where('provider', $provider)->exists(), 409);
            SocialIdentity::create(['user_id' => $u->id, 'provider' => $provider, 'subject_hash' => hash('sha256', $subject), 'subject' => $subject]);

            return $u;
        }, 3);
    }
}
