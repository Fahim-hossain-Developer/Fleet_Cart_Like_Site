<?php

namespace App\Http\Controllers;

use App\Domain\Customers\Actions\PhoneCode;
use App\Domain\Customers\Actions\ProductLists;
use Illuminate\Http\Request;

final class PhoneCodeController extends Controller
{
    public function show(): mixed
    {
        return view('themes.default.account.phone-code');
    }

    public function send(Request $r): mixed
    {
        $d = $r->validate(['phone' => 'required|string|max:32']);
        app(PhoneCode::class)->send($d['phone'], auth('web')->user());

        return back()->with('status', __('integrations.phone_sent'));
    }

    public function verify(Request $r): mixed
    {
        $d = $r->validate(['phone' => 'required|string|max:32', 'code' => 'required|digits:6']);
        $u = app(PhoneCode::class)->consume($d['phone'], $d['code'], auth('web')->user());
        auth('web')->login($u);
        $r->session()->regenerate();
        $r->session()->put('customer_auth_version', $u->auth_version);
        $r->session()->put('customer_phone_verified_until', now()->addMinutes(10)->timestamp);

        app(ProductLists::class)->mergeGuest($u);

        return redirect()->route('account');
    }
}
