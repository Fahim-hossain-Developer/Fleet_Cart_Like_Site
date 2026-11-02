<?php

namespace App\Http\Controllers;

use App\Domain\Customers\Actions\AdminCode;
use App\Models\Admin;
use Illuminate\Http\Request;

final class AdminCodeController extends Controller
{
    public function show(Request $r): mixed
    {
        abort_unless($r->session()->has('admin_otp_pending'), 403);

        return view('themes.default.account.admin-code');
    }

    public function verify(Request $r): mixed
    {
        $d = $r->validate(['code' => 'required|digits:6']);
        $c = app(AdminCode::class)->consume((string) $r->session()->get('admin_otp_pending'), $d['code']);
        $a = Admin::findOrFail($c->admin_id);
        auth('admin')->login($a, $c->remember);
        $r->session()->regenerate();
        $r->session()->forget('admin_otp_pending');
        $r->session()->put('admin_otp_verified', ['id' => $a->id, 'until' => now()->addHours(8)->timestamp, 'fingerprint' => $c->password_fingerprint]);

        return redirect('/admin');
    }
}
