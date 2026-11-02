<?php

use App\Http\Controllers\GatewayReturnController;
use App\Http\Controllers\GatewayWebhookController;
use App\Http\Controllers\NewsletterController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/r/{attempt:uuid}/{expires}/{token}', GatewayReturnController::class)->whereNumber('expires')->where('token', '[A-Za-z0-9_-]{43}')->middleware('throttle:120,1,integration-payment-return')->name('integrations.payment.return');
Route::post('/payments/{driver}/{mode}/webhook', GatewayWebhookController::class)->where('driver', 'stripe|paypal|sslcommerz|bkash|aamarpay|nagad|fake')->where('mode', 'sandbox|live')->middleware('throttle:1200,1,integration-payment-webhook')->name('integrations.payment.webhook');

Route::post('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'oneClick'])->middleware('throttle:60,1,newsletter-one-click')->name('newsletter.one-click');
