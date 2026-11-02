<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Customers\Actions\Sms;
use App\Domain\Customers\Models\SmsDelivery;
use App\Domain\Marketing\Models\CartRecoveryContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class RecoveryPhoneCode
{
    public function send(CartRecoveryContact $contact): void
    {
        abort_unless(store_setting('recovery_enabled', false) && $contact->phone && ! $contact->stopped_at && ! $contact->converted_at, 422);
        $key = 'cart-phone-code:'.$contact->phone_hash;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['phone' => __('orders.throttled')]);
        }RateLimiter::hit($key, 600);
        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($contact, $code) {
            $c = CartRecoveryContact::lockForUpdate()->findOrFail($contact->id);
            abort_if($c->stopped_at || $c->converted_at || $c->generation !== $contact->generation, 422);
            $d = app(Sms::class)->queue($c->phone, __('marketing.recovery_phone_code', ['code' => $code]));
            $c->update(['sms_code_hash' => Hash::make($code), 'sms_attempts' => 0, 'sms_expires_at' => now()->addMinutes(10), 'sms_delivery_id' => $d->id]);
        });
    }

    public function consume(CartRecoveryContact $contact, string $code): void
    {
        validator(compact('code'), ['code' => 'required|digits:6'])->validate();
        $ok = DB::transaction(function () use ($contact, $code) {
            $c = CartRecoveryContact::lockForUpdate()->findOrFail($contact->id);
            if ($c->generation !== $contact->generation || $c->stopped_at || $c->converted_at || ! $c->sms_code_hash || ! $c->sms_expires_at || now()->gte($c->sms_expires_at) || $c->sms_attempts >= 5) {
                return false;
            }$c->increment('sms_attempts');
            $d = SmsDelivery::find($c->sms_delivery_id);
            if (! $d || $d->status !== 'sent' || (app()->environment('production') && ($d->mode !== 'live' || $d->driver === 'sms_log')) || ! Hash::check($code, $c->sms_code_hash)) {
                return false;
            }$c->update(['phone_verified_at' => now(), 'sms_code_hash' => null, 'sms_expires_at' => null]);

            return true;
        });
        if (! $ok) {
            throw ValidationException::withMessages(['code' => __('orders.invalid_code')]);
        }
    }
}
