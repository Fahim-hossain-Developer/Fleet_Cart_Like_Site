<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Cart\Actions\CartSession;
use App\Domain\Cart\Models\Cart;
use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\CartRecoveryContact;
use App\Domain\Marketing\Models\CartRecoveryMessage;
use App\Domain\Marketing\Models\CartRecoveryRestoration;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RestoreCartRecovery
{
    /** @return array{restored:int,skipped:int} */
    public function execute(string $token): array
    {
        abort_unless(preg_match('/^[a-zA-Z0-9]{64}$/D', $token), 410);
        $message = CartRecoveryMessage::where('link_hash', hash('sha256', $token))->where('kind', 'reminder')->firstOrFail();
        $contact = CartRecoveryContact::findOrFail($message->cart_recovery_contact_id);
        $destination = app(CartSession::class)->current();

        return DB::transaction(function () use ($message, $contact, $destination) {
            Cart::whereIn('id', [$contact->cart_id, $destination->id])->orderBy('id')->lockForUpdate()->get();
            $c = CartRecoveryContact::lockForUpdate()->findOrFail($contact->id);
            $m = CartRecoveryMessage::lockForUpdate()->findOrFail($message->id);
            abort_if($c->stopped_at || $c->converted_at || $c->generation !== $m->generation || now()->gte($m->expires_at) || ($m->used_at && $m->restored_cart_id !== $destination->id), 410);
            if ($m->used_at || CartRecoveryRestoration::where('cart_recovery_contact_id', $c->id)->where('generation', $c->generation)->where('destination_cart_id', $destination->id)->exists()) {
                return ['restored' => 0, 'skipped' => 0];
            }$restored = 0;
            $skipped = 0;
            if ($destination->id !== $c->cart_id) {
                foreach ($m->snapshot ?? [] as $item) {
                    $p = Product::published()->find($item['product_id']);
                    if (! $p || $p->options()->where('required', true)->exists()) {
                        $skipped++;

                        continue;
                    }try {
                        app(CartManager::class)->add($destination, $p->id, $item['variant_id'], min(100, max(1, (int) $item['qty'])), []);
                        $restored++;
                    } catch (ValidationException|ModelNotFoundException $e) {
                        $skipped++;
                    }
                }
            }
            CartRecoveryRestoration::firstOrCreate(['cart_recovery_contact_id' => $c->id, 'generation' => $c->generation, 'destination_cart_id' => $destination->id]);
            $m->update(['used_at' => now(), 'restored_cart_id' => $destination->id, 'payload' => null]);

            return ['restored' => $restored, 'skipped' => $skipped];
        }, 3);
    }
}
