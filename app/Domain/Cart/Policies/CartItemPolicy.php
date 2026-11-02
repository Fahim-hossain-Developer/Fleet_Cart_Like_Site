<?php

namespace App\Domain\Cart\Policies;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Models\User;

class CartItemPolicy
{
    public function view(?User $user, CartItem $item): bool
    {
        $cart = Cart::find($item->cart_id);

        return $cart !== null && app(CartPolicy::class)->view($user, $cart);
    }

    public function update(?User $user, CartItem $item): bool
    {
        return $this->view($user, $item);
    }

    public function delete(?User $user, CartItem $item): bool
    {
        return $this->view($user, $item);
    }
}
