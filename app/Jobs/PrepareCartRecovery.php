<?php

namespace App\Jobs;

use App\Domain\Cart\Models\Cart;
use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\CartRecoveryContact;
use App\Domain\Marketing\Models\CartRecoveryMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class PrepareCartRecovery implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        if (! store_setting('recovery_enabled', false)) {
            return;
        }
        CartRecoveryContact::whereNull('stopped_at')->whereNull('converted_at')->whereNotNull('consented_at')->where('last_activity_at', '<=', now()->subHour())->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::transaction(function () use ($row) {
                    $cart = Cart::lockForUpdate()->find($row->cart_id);
                    if (! $cart) {
                        return;
                    }$c = CartRecoveryContact::lockForUpdate()->findOrFail($row->id);
                    if ($c->stopped_at || $c->converted_at) {
                        return;
                    }$last = $c->last_activity_at;
                    if ($cart->updated_at && (! $last || $cart->updated_at->gt($last))) {
                        $last = $cart->updated_at;
                    }if (! $last || $last->gt(now()->subHour()) || $last->lt(now()->subDays(7))) {
                        return;
                    }
                    $snapshot = [];
                    foreach ($cart->items()->where('saved', false)->orderBy('id')->get() as $item) {
                        if (Product::published()->whereKey($item->product_id)->exists()) {
                            $snapshot[] = ['product_id' => $item->product_id, 'variant_id' => $item->variant_id, 'qty' => $item->qty];
                        }
                    }if (! $snapshot) {
                        return;
                    }
                    $stage = 1;
                    foreach ([1, 24, 72] as $candidate) {
                        if ($last->lte(now()->subHours($candidate))) {
                            $stage = $candidate;
                        }
                    }foreach ([$stage] as $stage) {
                        foreach (['email', 'sms', 'whatsapp'] as $channel) {
                            if (empty($c->channels[$channel]) || ($channel === 'email' ? ! $c->email_verified_at : ! $c->phone_verified_at)) {
                                continue;
                            }if (CartRecoveryMessage::where('cart_recovery_contact_id', $c->id)->where('generation', $c->generation)->where('kind', 'reminder')->where('channel', $channel)->where('stage', $stage)->exists()) {
                                continue;
                            }$token = Str::random(64);
                            $m = CartRecoveryMessage::create(['cart_recovery_contact_id' => $c->id, 'kind' => 'reminder', 'channel' => $channel, 'stage' => $stage, 'generation' => $c->generation, 'payload' => ['token' => $token], 'snapshot' => $snapshot, 'link_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
                            DB::afterCommit(function () use ($m) {
                                try {
                                    DeliverCartRecovery::dispatch($m->id);
                                } catch (\Throwable $e) {
                                    Log::warning('Cart reminder pending', ['message_id' => $m->id]);
                                }
                            });
                        }
                    }
                });
            }
        });
    }
}
