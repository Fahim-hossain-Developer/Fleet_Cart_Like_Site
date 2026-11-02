<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $record = DB::table('account_setup_tokens')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            abort_unless($record && ! $record->used_at && now()->lt($record->expires_at), 404);
            User::findOrFail($record->user_id)->update(['password' => $data['password']]);
            DB::table('account_setup_tokens')->where('id', $record->id)->update(['used_at' => now()]);
        });

        return redirect()->route('home')->with('status', __('checkout.password_set'));
    }
}
