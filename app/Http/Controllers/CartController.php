<?php

namespace App\Http\Controllers;

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Cart\Actions\CartSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function add(Request $request, CartSession $session, CartManager $manager): RedirectResponse
    {
        $data = $request->validate(['product_id' => 'required|integer', 'variant_id' => 'nullable|integer', 'quantity' => 'required|integer|min:1|max:100', 'options' => 'array', 'buy_now' => 'nullable|boolean']);
        $options = $request->input('options', []);
        foreach ($request->file('options', []) as $key => $file) {
            $options[$key] = $file;
        }
        $manager->add($session->current(), (int) $data['product_id'], ! empty($data['variant_id']) ? (int) $data['variant_id'] : null, (int) $data['quantity'], $options);

        return redirect()->route($request->boolean('buy_now') ? 'checkout' : 'cart')->with('status', __('checkout.added'));
    }
}
