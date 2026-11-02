<?php

namespace App\Http\Controllers;

use App\Domain\Marketing\Actions\Newsletter;
use Illuminate\Http\Request;

final class NewsletterController extends Controller
{
    public function subscribe(Request $r): mixed
    {
        app(Newsletter::class)->subscribe($r->all());

        return back()->with('status', __('marketing.newsletter_check_email'));
    }

    public function confirm(string $token): mixed
    {
        $valid = app(Newsletter::class)->confirm($token);

        return response()->view('themes.default.marketing.newsletter-status', ['confirmed' => $valid, 'unsubscribed' => false], $valid ? 200 : 410)->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function unsubscribe(Request $r, string $token): mixed
    {
        if ($r->isMethod('post')) {
            app(Newsletter::class)->unsubscribe($token);

            return response()->view('themes.default.marketing.newsletter-status', ['confirmed' => false, 'unsubscribed' => true])->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return response()->view('themes.default.marketing.newsletter-unsubscribe', ['token' => $token])->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function oneClick(string $token): mixed
    {
        app(Newsletter::class)->unsubscribe($token);

        return response()->json(['unsubscribed' => true]);
    }
}
