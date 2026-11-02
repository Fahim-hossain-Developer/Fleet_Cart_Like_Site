<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Cart\Models\Cart;
use App\Domain\Marketing\Models\CartRecoveryContact;
use App\Domain\Marketing\Models\CartRecoveryRestoration;

final class RecoveryConversion
{
    // Caller already holds the placed order's cart lock.
    public function mark(Cart $cart): void
    {
        CartRecoveryContact::where('cart_id', $cart->id)->update(['converted_at' => now(), 'confirmation_hash' => null, 'sms_code_hash' => null]);
        foreach (CartRecoveryRestoration::where('destination_cart_id', $cart->id)->orderBy('cart_recovery_contact_id')->get() as $r) {
            CartRecoveryContact::whereKey($r->cart_recovery_contact_id)->where('generation', $r->generation)->update(['converted_at' => now(), 'confirmation_hash' => null, 'sms_code_hash' => null]);
        }
    }
}
