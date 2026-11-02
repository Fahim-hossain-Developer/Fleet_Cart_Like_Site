<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', $request->cookie('locale', Auth::guard('web')->user()->locale ?? config('app.locale')));
        if ($request->isMethod('get') && in_array($request->query('lang'), ['en', 'bn'], true)) {
            $locale = $request->query('lang');
        }
        app()->setLocale(in_array($locale, ['en', 'bn'], true) ? $locale : 'en');

        return $next($request);
    }
}
