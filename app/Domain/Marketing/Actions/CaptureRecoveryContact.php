<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Cart\Models\Cart;
use App\Domain\Customers\Actions\NormalizePhone;
use App\Domain\Marketing\Models\CartRecoveryContact;
use App\Domain\Marketing\Models\CartRecoveryMessage;
use App\Jobs\DeliverCartRecovery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CaptureRecoveryContact
{
    /** @param array<string,mixed> $input */
    public function execute(Cart $cart, array $input): ?CartRecoveryContact
    {
        $enabled = validator($input, ['enabled' => 'required|boolean'])->validate()['enabled'];
        if (! $enabled) {
            CartRecoveryContact::where('cart_id', $cart->id)->update(['stopped_at' => now(), 'confirmation_hash' => null]);

            return null;
        }
        abort_unless(store_setting('recovery_enabled', false), 422);
        $d = validator($input, ['name' => 'nullable|string|max:100', 'email' => 'nullable|email:rfc|max:254', 'phone' => 'nullable|string|max:32', 'channels' => 'required|array:email,sms,whatsapp', 'channels.*' => 'boolean', 'consent' => 'accepted'])->validate();
        $channels = array_map(fn ($v) => (bool) $v, $d['channels']);
        if (! array_filter($channels)) {
            throw ValidationException::withMessages(['channels' => __('marketing.choose_recovery_channel')]);
        }
        $email = ! empty($d['email']) ? strtolower(trim($d['email'])) : null;
        $phone = ! empty($d['phone']) ? app(NormalizePhone::class)->execute($d['phone']) : null;
        if ((! empty($channels['email']) && ! $email) || ((! empty($channels['sms']) || ! empty($channels['whatsapp'])) && ! $phone)) {
            throw ValidationException::withMessages(['channels' => __('marketing.recovery_contact_required')]);
        }
        $u = auth('web')->user();
        if ($u) {
            abort_if($u->is_blocked, 403);
        }

        return DB::transaction(function () use ($cart, $channels, $email, $phone, $d, $u) {
            Cart::lockForUpdate()->findOrFail($cart->id);
            abort_unless($cart->items()->where('saved', false)->exists(), 422);
            CartRecoveryContact::upsert([['cart_id' => $cart->id, 'channels' => '{}', 'generation' => 0, 'locale' => app()->getLocale(), 'created_at' => now(), 'updated_at' => now()]], ['cart_id'], ['updated_at']);
            $c = CartRecoveryContact::where('cart_id', $cart->id)->lockForUpdate()->firstOrFail();
            $same = $c->generation > 0 && $c->email === $email && $c->phone === $phone && $c->channels === $channels && ! $c->stopped_at && ! $c->converted_at;
            if ($same) {
                $c->update(['captured_name' => $d['name'] ?? null, 'last_activity_at' => now()]);

                return $c;
            }
            $emailVerified = ($email && $c->email === $email && $c->email_verified_at) || ($u && $email && $u->email_verified_at && $u->email === $email);
            $phoneVerified = ($phone && $c->phone === $phone && $c->phone_verified_at) || ($u && $phone && $u->phone_verified_at && $u->phone === $phone);
            $stop = Str::random(64);
            $c->update(['user_id' => $u?->id, 'captured_name' => $d['name'] ?? null, 'email' => $email, 'phone' => $phone, 'email_hash' => $email ? hash_hmac('sha256', $email, config('app.key')) : null, 'phone_hash' => $phone ? hash_hmac('sha256', $phone, config('app.key')) : null, 'channels' => $channels, 'generation' => $c->generation + 1, 'locale' => app()->getLocale(), 'consented_at' => now(), 'last_activity_at' => now(), 'email_verified_at' => $emailVerified ? now() : null, 'phone_verified_at' => $phoneVerified ? now() : null, 'confirmation_hash' => null, 'confirmation_expires_at' => null, 'sms_code_hash' => null, 'sms_attempts' => 0, 'sms_expires_at' => null, 'sms_delivery_id' => null, 'stop_token' => $stop, 'stop_hash' => hash('sha256', $stop), 'stopped_at' => null, 'converted_at' => null]);
            if (! empty($channels['email']) && ! $emailVerified) {
                $key = 'recovery-confirm:'.$c->email_hash;
                if (RateLimiter::tooManyAttempts($key, 3)) {
                    throw ValidationException::withMessages(['email' => __('orders.throttled')]);
                }RateLimiter::hit($key, 86400);
                $token = Str::random(64);
                $c->update(['confirmation_hash' => hash('sha256', $token), 'confirmation_expires_at' => now()->addDay()]);
                $m = CartRecoveryMessage::create(['cart_recovery_contact_id' => $c->id, 'kind' => 'confirmation', 'channel' => 'email', 'generation' => $c->generation, 'payload' => ['token' => $token], 'expires_at' => now()->addDay()]);
                DB::afterCommit(function () use ($m) {
                    try {
                        DeliverCartRecovery::dispatch($m->id);
                    } catch (\Throwable $e) {
                        Log::warning('Cart contact confirmation pending', ['message_id' => $m->id]);
                    }
                });
            }

            return $c;
        }, 3);
    }

    public function confirm(string $token): bool
    {
        if (! preg_match('/^[a-zA-Z0-9]{64}$/D', $token)) {
            return false;
        }

        return DB::transaction(function () use ($token) {
            $c = CartRecoveryContact::where('confirmation_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $c || $c->stopped_at || $c->converted_at || ! $c->confirmation_expires_at || now()->gte($c->confirmation_expires_at)) {
                return false;
            }$c->update(['email_verified_at' => now(), 'confirmation_hash' => null, 'confirmation_expires_at' => null]);

            return true;
        });
    }

    public function stop(string $token): void
    {
        if (preg_match('/^[a-zA-Z0-9]{64}$/D', $token)) {
            CartRecoveryContact::where('stop_hash', hash('sha256', $token))->update(['stopped_at' => now(), 'confirmation_hash' => null, 'sms_code_hash' => null]);
        }
    }
}
