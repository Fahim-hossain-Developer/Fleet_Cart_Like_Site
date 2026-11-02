<?php

namespace App\Http\Controllers;

use App\Domain\Cart\Actions\CartSession;
use App\Domain\Customers\Actions\EmailCode;
use App\Domain\Customers\Actions\NormalizePhone;
use App\Domain\Customers\Actions\ProductLists;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CustomerAuthController extends Controller
{
    public function login(Request $r): mixed
    {
        $d = $r->validate(['identifier' => 'required|string|max:254', 'password' => 'required|string', 'remember' => 'sometimes|boolean']);
        $field = filter_var($d['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $id = $field === 'email' ? strtolower($d['identifier']) : app(NormalizePhone::class)->execute($d['identifier']);
        $r->session()->forget('customer_email_verified_until');
        if (! Auth::guard('web')->attempt([$field => $id, 'password' => $d['password'], 'is_blocked' => false], $r->boolean('remember'))) {
            throw ValidationException::withMessages(['identifier' => __('auth.failed')]);
        }

        return $this->signedIn($r);
    }

    private function signedIn(Request $r): mixed
    {
        $r->session()->regenerate();
        $r->session()->put('customer_auth_version', Auth::guard('web')->user()->auth_version);
        Auth::guard('web')->user()->forceFill(['last_login_at' => now()])->save();
        app(CartSession::class)->current();
        app(ProductLists::class)->mergeGuest(Auth::guard('web')->user());

        return redirect()->intended(route('account'));
    }

    public function register(Request $r): mixed
    {
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email:rfc|max:254|unique:users,email', 'phone' => 'nullable|string|max:32', 'password' => 'required|confirmed|min:12|max:128']);
        $d['email'] = strtolower($d['email']);
        if (! empty($d['phone'])) {
            $d['phone'] = app(NormalizePhone::class)->execute($d['phone']);
            validator($d, ['phone' => 'unique:users,phone'])->validate();
        }
        User::create($d);
        app(EmailCode::class)->send($d['email']);

        return redirect()->route('otp')->with('status', __('orders.code_sent'));
    }

    public function sendCode(Request $r): mixed
    {
        $d = $r->validate(['email' => 'required|email:rfc|max:254']);
        app(EmailCode::class)->send($d['email']);

        return back()->with('status', __('orders.code_sent'));
    }

    public function verifyCode(Request $r): mixed
    {
        $d = $r->validate(['email' => 'required|email:rfc|max:254', 'code' => 'required|digits:6']);
        $u = app(EmailCode::class)->consume($d['email'], $d['code']);
        Auth::guard('web')->login($u);
        $r->session()->put('customer_email_verified_until', now()->addMinutes(10)->timestamp);

        return $this->signedIn($r);
    }

    public function logout(Request $r): mixed
    {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }

    public function forgot(Request $r): mixed
    {
        $d = $r->validate(['email' => 'required|email:rfc|max:254']);
        Password::broker('users')->sendResetLink($d);

        return back()->with('status', __('orders.reset_sent'));
    }

    public function reset(Request $r): mixed
    {
        $d = $r->validate(['email' => 'required|email:rfc|max:254', 'token' => 'required|string', 'password' => 'required|confirmed|min:12|max:128']);
        $status = Password::broker('users')->reset($d, function (User $u, string $password) {
            $u->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'auth_version' => $u->auth_version + 1])->save();
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
