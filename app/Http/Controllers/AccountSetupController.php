<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountSetupController extends Controller
{
    public function show(string $token): View
    {
        $this->find($token);

        return view('themes.default.account-setup', ['token' => $token]);
    }

    private function find(string $token): object
    {
        $record = DB::table('account_setup_tokens')->where('token_hash', hash('sha256', $token))->whereNull('used_at')->where('expires_at', '>', now())->first();
        abort_unless($record !== null, 404);

        return $record;
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate(['password' => 'required|string|min:12|max:128|confirmed']);
        DB::transaction(function () use ($token, $data): void {
            $candidate = DB::table('account_setup_tokens')->where('token_hash', hash('sha256', $token))->first();
            abort_unless($candidate !== null, 404);
            $user = User::lockForUpdate()->findOrFail((int) $candidate->user_id);
            $record = DB::table('account_setup_tokens')->where('id', $candidate->id)->lockForUpdate()->first();
            abort_unless($record && ! $record->used_at && now()->lt($record->expires_at), 404);
            $user->forceFill(['password' => $data['password'], 'auth_version' => $user->auth_version + 1, 'remember_token' => Str::random(60)])->save();
            DB::table('account_setup_tokens')->where('id', $record->id)->update(['used_at' => now()]);
        }, 3);

        return redirect()->route('home')->with('status', __('checkout.password_set'));
    }
}
