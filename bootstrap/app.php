<?php

use App\Http\Middleware\CustomerActive;
use App\Http\Middleware\RequireAdminOtp;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SingleStore;
use App\Http\Middleware\ThemePreview;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')->prefix('integrations')->group(base_path('routes/integrations.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class, ThemePreview::class, SingleStore::class, CustomerActive::class, RequireAdminOtp::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
