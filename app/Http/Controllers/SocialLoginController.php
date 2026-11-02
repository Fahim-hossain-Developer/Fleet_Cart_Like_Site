<?php

namespace App\Http\Controllers;

use App\Domain\Customers\Actions\ProductLists;
use App\Domain\Customers\Actions\SocialLogin;
use App\Domain\Payments\Actions\IntegrationFailure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

final class SocialLoginController extends Controller
{
    public function start(Request $r, string $provider): mixed
    {
        return redirect()->away(app(SocialLogin::class)->begin($provider, $r->boolean('link')))->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function callback(Request $r, string $provider): mixed
    {
        $d = $r->validate(['state' => 'required|string|max:128', 'code' => 'required|string|max:4096']);
        try {
            $u = app(SocialLogin::class)->callback($provider, $d['state'], $d['code']);
        } catch (IntegrationFailure $e) {
            return redirect()->route('login')->withErrors(['social' => __('marketing.social_failed')]);
        } catch (UniqueConstraintViolationException $e) {
            return redirect()->route('login')->withErrors(['social' => __('marketing.social_existing')]);
        }
        auth('web')->login($u);
        $r->session()->forget(['customer_email_verified_until', 'customer_phone_verified_until']);
        $r->session()->regenerate();
        $r->session()->put('customer_auth_version', $u->auth_version);
        app(ProductLists::class)->mergeGuest($u);

        return redirect()->route('account')->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
