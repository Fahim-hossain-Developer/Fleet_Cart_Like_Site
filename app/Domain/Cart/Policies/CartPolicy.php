<?php

namespace App\Domain\Cart\Policies;

use App\Domain\Cart\Models\Cart;
use App\Models\User;

class CartPolicy
{
    public function view(?User $user, Cart $cart): bool
    {
        return $cart->user_id ? $user?->id === $cart->user_id : hash_equals($cart->token, (string) session('cart_token', ''));
    }

    public function update(?User $user, Cart $cart): bool
    {
        return $this->view($user, $cart);
    }

    public function delete(?User $user, Cart $cart): bool
    {
        return $this->view($user, $cart);
    }
}
