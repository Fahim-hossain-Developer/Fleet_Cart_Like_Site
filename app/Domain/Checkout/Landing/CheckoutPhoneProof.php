<?php

namespace App\Domain\Checkout\Landing;

use App\Domain\Cart\Models\Cart;
use App\Domain\Checkout\Models\CheckoutPhoneChallenge;
use App\Domain\Customers\Actions\NormalizePhone;
use App\Domain\Customers\Actions\Sms;
use App\Domain\Customers\Models\SmsDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CheckoutPhoneProof
{
    public function phoneHash(string $phone): string
    {
        return hash_hmac('sha256', app(NormalizePhone::class)->execute($phone), config('app.key'));
    }

    public function send(Cart $cart, string $phone): void
    {
        $phone = app(NormalizePhone::class)->execute($phone);
        $hash = $this->phoneHash($phone);
        $limit = 'landing-phone-proof:'.$hash;
        if (RateLimiter::tooManyAttempts($limit, 3)) {
            throw ValidationException::withMessages(['phone' => __('orders.throttled')]);
        }RateLimiter::hit($limit, 600);
        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($cart, $phone, $hash, $code) {
            Cart::lockForUpdate()->findOrFail($cart->id);
            CheckoutPhoneChallenge::where('cart_id', $cart->id)->whereNull('used_at')->update(['used_at' => now(), 'code_hash' => null]);
            $sms = app(Sms::class)->queue($phone, __('landing.phone_code_message', ['code' => $code]));
            CheckoutPhoneChallenge::create(['uuid' => (string) Str::uuid(), 'cart_id' => $cart->id, 'phone_hash' => $hash, 'code_hash' => Hash::make($code), 'sms_delivery_id' => $sms->id, 'expires_at' => now()->addMinutes(10)]);
        });
    }

    public function verify(Cart $cart, string $phone, string $code): void
    {
        validator(compact('code'), ['code' => 'required|digits:6'])->validate();
        $hash = $this->phoneHash($phone);
        $ok = DB::transaction(function () use ($cart, $hash, $code) {
            Cart::lockForUpdate()->findOrFail($cart->id);
            $p = CheckoutPhoneChallenge::where('cart_id', $cart->id)->where('phone_hash', $hash)->whereNull('used_at')->whereNull('verified_at')->latest('id')->lockForUpdate()->first();
            if (! $p || $p->attempts >= 5 || now()->gte($p->expires_at) || ! $p->code_hash) {
                return false;
            }
            $p->increment('attempts');
            $sms = SmsDelivery::find($p->sms_delivery_id);
            if (! $sms || $sms->status !== 'sent' || (app()->environment('production') && ($sms->mode !== 'live' || $sms->driver === 'sms_log')) || ! Hash::check($code, $p->code_hash)) {
                return false;
            }
            $p->update(['verified_at' => now(), 'code_hash' => null]);

            return true;
        });
        if (! $ok) {
            throw ValidationException::withMessages(['code' => __('orders.invalid_code')]);
        }
    }

    // Called inside the cart/order placement transaction. Failed checkout rolls consumption back.
    public function consume(Cart $cart, string $phone): void
    {
        $p = CheckoutPhoneChallenge::where('cart_id', $cart->id)->where('phone_hash', $this->phoneHash($phone))->whereNull('used_at')->whereNotNull('verified_at')->where('expires_at', '>', now())->latest('id')->lockForUpdate()->first();
        if (! $p) {
            throw ValidationException::withMessages(['phone' => __('landing.phone_proof_required')]);
        }$p->update(['used_at' => now()]);
    }
}
