<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Models\AdminLoginChallenge;
use App\Jobs\SendAdminCode;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AdminCode
{
    public function required(Admin $admin): bool
    {
        return $admin->hasAnyRole(store_setting('admin_otp_roles', []));
    }

    public function fingerprint(Admin $admin): string
    {
        return hash('sha256', $admin->password.'|'.$admin->email);
    }

    public function begin(Admin $admin, bool $remember = false): AdminLoginChallenge
    {
        $key = 'admin-code:'.$admin->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['data.email' => __('orders.throttled')]);
        }RateLimiter::hit($key, 600);
        if (! app()->environment('local', 'testing') && in_array(config('mail.default'), ['log', 'array'], true)) {
            throw ValidationException::withMessages(['data.email' => __('integrations.real_mail_required')]);
        }
        $code = (string) random_int(100000, 999999);
        $c = DB::transaction(function () use ($admin, $remember, $code) {
            Admin::lockForUpdate()->findOrFail($admin->id);
            AdminLoginChallenge::where('admin_id', $admin->id)->whereNull('used_at')->update(['used_at' => now(), 'delivery_payload' => null]);

            return AdminLoginChallenge::create(['uuid' => (string) Str::uuid(), 'admin_id' => $admin->id, 'code_hash' => Hash::make($code), 'delivery_payload' => $code, 'remember' => $remember, 'password_fingerprint' => $this->fingerprint($admin), 'expires_at' => now()->addMinutes(10)]);
        });
        session()->put('admin_otp_pending', $c->uuid);
        session()->forget('admin_otp_verified');
        SendAdminCode::dispatch($c->id);

        return $c;
    }

    public function deliver(int $id): void
    {
        $c = DB::transaction(function () use ($id) {
            $c = AdminLoginChallenge::lockForUpdate()->find($id);
            if (! $c || $c->used_at || $c->sent_at || $c->delivery_attempts >= 3 || now()->gte($c->expires_at)) {
                return null;
            }$c->increment('delivery_attempts');

            return $c;
        });
        if (! $c) {
            return;
        }
        try {
            $a = Admin::findOrFail($c->admin_id);
            if (! hash_equals((string) $c->password_fingerprint, $this->fingerprint($a))) {
                return;
            }Mail::raw(__('integrations.admin_code_message', ['code' => $c->delivery_payload]), fn ($m) => $m->to($a->email)->subject(__('integrations.admin_otp')));
            $c->update(['sent_at' => now(), 'delivery_payload' => null]);
        } catch (\Throwable $e) {
            Log::warning('Admin code delivery pending', ['challenge_id' => $id]);
        }
    }

    public function consume(string $uuid, string $code): AdminLoginChallenge
    {
        $id = DB::transaction(function () use ($uuid, $code) {
            $c = AdminLoginChallenge::where('uuid', $uuid)->lockForUpdate()->first();
            if (! $c || $c->used_at || ! $c->sent_at || now()->gte($c->expires_at) || $c->attempts >= 5) {
                return null;
            }$c->increment('attempts');
            $a = Admin::find($c->admin_id);
            if (! $a || ! $a->is_active || ! $a->hasPermissionTo('admin.access', 'admin') || ! hash_equals((string) $c->password_fingerprint, $this->fingerprint($a)) || ! Hash::check($code, $c->code_hash)) {
                return null;
            }$c->update(['used_at' => now(), 'delivery_payload' => null]);

            return $c->id;
        }, 3);
        if (! $id) {
            throw ValidationException::withMessages(['code' => __('orders.invalid_code')]);
        }

        return AdminLoginChallenge::findOrFail($id);
    }

    public function verified(Admin $a): bool
    {
        $p = session('admin_otp_verified', []);

        return ($p['id'] ?? null) === $a->id && ($p['until'] ?? 0) > now()->timestamp && hash_equals($this->fingerprint($a), (string) ($p['fingerprint'] ?? ''));
    }
}
