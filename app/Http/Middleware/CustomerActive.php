<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('web')->check() && ! $request->session()->has('customer_auth_version')) {
            $request->session()->put('customer_auth_version', auth('web')->user()->auth_version);
        }
        if (auth('web')->check() && (auth('web')->user()->is_blocked || (int) $request->session()->get('customer_auth_version') !== auth('web')->user()->auth_version)) {
            auth('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, __('orders.blocked'));
        }

        return $next($request);
    }
}
