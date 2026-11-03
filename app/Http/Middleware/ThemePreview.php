<?php

namespace App\Http\Middleware;

use App\Domain\Settings\Theme\Theme;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ThemePreview
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->has('theme_preview')) {
            return $next($request);
        }
        abort_unless($request->isMethod('get'), 403);
        app(Theme::class)->current();

        return $next($request)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }
}
