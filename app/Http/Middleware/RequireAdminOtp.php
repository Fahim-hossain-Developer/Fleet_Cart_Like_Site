<?php

namespace App\Http\Middleware;

use App\Domain\Customers\Actions\AdminCode;
use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireAdminOtp
{
    public function handle(Request $request, Closure $next): Response
    {
        $a = auth('admin')->user();
        if ($a instanceof Admin && app(AdminCode::class)->required($a) && ! app(AdminCode::class)->verified($a)) {
            auth('admin')->logout();
            $request->session()->forget('admin_otp_verified');

            return redirect('/admin/login');
        }

        return $next($request);
    }
}
