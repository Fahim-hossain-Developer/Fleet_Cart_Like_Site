<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*') || ($request->is('livewire/*') && auth('admin')->check())) {
            return $next($request);
        }
        if (auth('web')->check() && ! $request->session()->has('customer_auth_version')) {
            $request->session()->put('customer_auth_version', auth('web')->user()->auth_version);
        }
        if (auth('web')->check() && (auth('web')->user()->is_blocked || (int) $request->session()->get('customer_auth_version') !== auth('web')->user()->auth_version)) {
            auth('web')->logout();
            if (auth('admin')->check() || $request->session()->has('admin_otp_pending')) {
                $request->session()->forget(['customer_auth_version', 'customer_email_verified_until']);
                $request->session()->regenerate();
            } else {
                $request->session()->invalidate();
            }
            $request->session()->regenerateToken();
            abort(403, __('orders.blocked'));
        }

        return $next($request);
    }
}
