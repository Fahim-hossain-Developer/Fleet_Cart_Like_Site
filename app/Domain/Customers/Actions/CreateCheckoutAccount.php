<?php

namespace App\Domain\Customers\Actions;

use App\Jobs\SendAccountSetup;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class CreateCheckoutAccount
{
    /** @param array<string,mixed> $data */
    public function execute(array $data): ?User
    {
        if (Auth::guard('web')->check()) {
            return Auth::guard('web')->user();
        }
        if (! ($data['create_account'] ?? false) && ! store_setting('checkout_auto_account', config('checkout.auto_account'))) {
            return null;
        }
        $existing = User::where('phone', $data['phone'])->when(! empty($data['email']), fn ($q) => $q->orWhere('email', $data['email']))->exists();
        if ($existing) {
            return null;
        }
        try {
            $user = DB::transaction(fn () => User::create(['name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null, 'password' => ! empty($data['password']) ? $data['password'] : Str::random(64), 'locale' => app()->getLocale(), 'currency' => session('display_currency', config('checkout.base_currency'))]));
        } catch (UniqueConstraintViolationException $e) {
            return null;
        }
        $token = Str::random(64);
        $deliveryId = DB::table('account_setup_tokens')->insertGetId(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'delivery_payload' => Crypt::encryptString($token), 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
        DB::afterCommit(function () use ($deliveryId): void {
            try {
                SendAccountSetup::dispatch($deliveryId);
            } catch (\Throwable $e) {
                Log::warning('Account setup delivery pending retry', ['delivery_id' => $deliveryId]);
            }
        });

        // Account creation is not proof of email/phone ownership; never auto-login.
        return $user;
    }
}
