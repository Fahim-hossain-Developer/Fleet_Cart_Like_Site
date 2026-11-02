<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EmailCode
{
    public function send(string $email): void
    {
        $email = strtolower(trim($email));
        $key = 'email-code:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['email' => __('orders.throttled')]);
        }
        RateLimiter::hit($key, 600);
        $user = User::where('email', $email)->where('is_blocked', false)->first();
        if (! $user) {
            return;
        }
        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($email, $code) {
            User::where('email', $email)->lockForUpdate()->firstOrFail();
            DB::table('otp_codes')->updateOrInsert(['identifier' => $email], ['code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10), 'used_at' => null, 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
        });
        try {
            Mail::raw(__('orders.email_code_message', ['code' => $code]), fn ($m) => $m->to($email)->subject(__('orders.email_code')));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function consume(string $email, string $code): User
    {
        $email = strtolower(trim($email));
        // Commit failed-attempt counters as well as successful consumption.
        $id = DB::transaction(function () use ($email, $code) {
            $otp = DB::table('otp_codes')->where('identifier', $email)->lockForUpdate()->first();
            if (! $otp || $otp->used_at || now()->gte($otp->expires_at) || $otp->attempts >= 5) {
                return null;
            }
            DB::table('otp_codes')->where('id', $otp->id)->increment('attempts');
            if (! Hash::check($code, $otp->code_hash)) {
                return null;
            }
            $u = User::where('email', $email)->where('is_blocked', false)->lockForUpdate()->first();
            if (! $u) {
                return null;
            }
            DB::table('otp_codes')->where('id', $otp->id)->update(['used_at' => now()]);
            if (! $u->email_verified_at) {
                // A password chosen before contact verification cannot remain a back door after a different person proves email ownership.
                $u->forceFill(['password' => Str::random(64), 'remember_token' => Str::random(60), 'auth_version' => $u->auth_version + 1]);
                DB::table('account_setup_tokens')->where('user_id', $u->id)->whereNull('used_at')->update(['used_at' => now(), 'delivery_payload' => null]);
            }
            $u->forceFill(['email_verified_at' => now()])->save();
            Order::whereNull('user_id')->where('guest_email', $email)->update(['user_id' => $u->id]);

            return $u->id;
        }, 3);
        if (! $id) {
            throw ValidationException::withMessages(['code' => __('orders.invalid_code')]);
        }

        return User::findOrFail($id);
    }
}
