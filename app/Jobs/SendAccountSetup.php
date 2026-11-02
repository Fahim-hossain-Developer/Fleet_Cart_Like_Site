<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAccountSetup implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $row = DB::table('account_setup_tokens')->where('id', $this->deliveryId)->first();
        if (! $row || $row->sent_at || $row->used_at || ! $row->delivery_payload || now()->gte($row->expires_at)) {
            return;
        }
        $user = User::findOrFail($row->user_id);
        $token = Crypt::decryptString($row->delivery_payload);
        $url = route('account.setup', ['token' => $token]);
        DB::table('account_setup_tokens')->where('id', $row->id)->increment('attempts');
        if ($user->email) {
            Mail::raw(__('checkout.setup_message').' '.$url, fn ($m) => $m->to($user->email)->subject(__('checkout.setup_subject')));
        } else {
            Log::info('Local SMS account setup', ['to' => $user->phone, 'url' => $url]);
        }
        DB::table('account_setup_tokens')->where('id', $row->id)->update(['sent_at' => now(), 'delivery_payload' => null, 'updated_at' => now()]);
    }
}
