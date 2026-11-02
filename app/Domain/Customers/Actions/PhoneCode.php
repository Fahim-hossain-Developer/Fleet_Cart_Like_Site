<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Models\PhoneChallenge;
use App\Domain\Customers\Models\SmsDelivery;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PhoneCode
{
    private function scope(string $phone, ?User $owner): string
    {
        return hash('sha256', ($owner ? 'verify:'.$owner->id : 'login').':'.$phone);
    }

    public function send(string $phone, ?User $owner = null): void
    {
        $phone = app(NormalizePhone::class)->execute($phone);
        $limit = 'phone-code:'.hash('sha256', $phone);
        if (RateLimiter::tooManyAttempts($limit, 3)) {
            throw ValidationException::withMessages(['phone' => __('orders.throttled')]);
        }RateLimiter::hit($limit, 600);
        $existing = User::where('phone', $phone)->first();
        if (($owner && $existing && $existing->id !== $owner->id) || (! $owner && $existing && ($existing->is_blocked || ($existing->email_verified_at && ! $existing->phone_verified_at)))) {
            return;
        }
        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($phone, $owner, $code) {
            $d = app(Sms::class)->queue($phone, __('integrations.phone_code_message', ['code' => $code]));
            PhoneChallenge::upsert([['scope_key' => $this->scope($phone, $owner), 'phone' => $phone, 'purpose' => $owner ? 'verify' : 'login', 'user_id' => $owner?->id, 'code_hash' => Hash::make($code), 'attempts' => 0, 'expires_at' => now()->addMinutes(10), 'used_at' => null, 'sms_delivery_id' => $d->id, 'created_at' => now(), 'updated_at' => now()]], ['scope_key'], ['code_hash', 'attempts', 'expires_at', 'used_at', 'sms_delivery_id', 'updated_at']);
        });
    }

    public function consume(string $phone, string $code, ?User $owner = null): User
    {
        $phone = app(NormalizePhone::class)->execute($phone);
        $id = DB::transaction(function () use ($phone, $code, $owner) {
            $c = PhoneChallenge::where('scope_key', $this->scope($phone, $owner))->lockForUpdate()->first();
            if (! $c || $c->used_at || now()->gte($c->expires_at) || $c->attempts >= 5) {
                return null;
            }
            $c->increment('attempts');
            $d = SmsDelivery::find($c->sms_delivery_id);
            if (! $d || $d->status !== 'sent' || (app()->environment('production') && ($d->mode !== 'live' || $d->driver === 'sms_log')) || ! Hash::check($code, $c->code_hash)) {
                return null;
            }
            $u = User::where('phone', $phone)->lockForUpdate()->first();
            if ($owner) {
                if ($u && $u->id !== $owner->id) {
                    return null;
                }$u = User::lockForUpdate()->findOrFail($owner->id);
            } elseif ($u && $u->email_verified_at && ! $u->phone_verified_at) {
                return null;
            }
            if ($u?->is_blocked) {
                return null;
            }
            if (! $u) {
                $u = User::create(['name' => __('integrations.phone_customer'), 'phone' => $phone, 'password' => Str::random(64)]);
            }
            if (! $owner && ! $u->phone_verified_at) {
                // Discard an unverified email supplied before someone else proved phone ownership.
                if ($u->email && ! $u->email_verified_at) {
                    DB::table('otp_codes')->where('identifier', $u->email)->update(['used_at' => now()]);
                    $u->email = null;
                }
                $u->forceFill(['password' => Str::random(64), 'remember_token' => Str::random(60), 'auth_version' => $u->auth_version + 1]);
                DB::table('account_setup_tokens')->where('user_id', $u->id)->whereNull('used_at')->update(['used_at' => now(), 'delivery_payload' => null]);
            }
            $u->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();
            $c->update(['used_at' => now()]);
            Order::whereNull('user_id')->where('phone', $phone)->update(['user_id' => $u->id]);

            return $u->id;
        }, 3);
        if (! $id) {
            throw ValidationException::withMessages(['code' => __('orders.invalid_code')]);
        }

        return User::findOrFail($id);
    }
}
