<?php

namespace App\Livewire;

use App\Domain\Cart\Actions\CartSession;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MiniCart extends Component
{
    public function render(): View
    {
        $cart = app(CartSession::class)->current();

        return view('livewire.mini-cart', ['items' => $cart->items()->with('product')->where('saved', false)->get()]);
    }
}
