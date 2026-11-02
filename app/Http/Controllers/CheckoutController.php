<?php

namespace App\Http\Controllers;

use App\Domain\Cart\Actions\CartSession;
use App\Domain\Checkout\Actions\CheckoutQuote;
use App\Domain\Customers\Models\Address;
use App\Domain\Orders\Actions\PlaceOrder;
use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Models\Location;
use App\Http\Requests\CheckoutRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function show(Request $request, CartSession $session): View|RedirectResponse
    {
        $cart = $session->current();
        if (! $cart->items()->where('saved', false)->exists()) {
            return redirect()->route('cart');
        }
        $key = session('checkout_key');
        if (! is_string($key)) {
            $key = (string) Str::uuid();
            session(['checkout_key' => $key]);
        }

        return view('themes.default.checkout', ['addresses' => auth('web')->check() ? Address::where('user_id', auth('web')->id())->orderByDesc('is_default')->get() : collect(), 'key' => $key, 'divisions' => Location::where('type', 'division')->get(), 'paymentMethods' => store_setting('checkout_payment_methods', config('checkout.payment_methods'))]);
    }

    public function quote(Request $request, CartSession $session): JsonResponse
    {
        $data = $request->validate(['country' => 'required|in:BD', 'district_id' => 'required|integer', 'shipping_method_id' => 'required|integer', 'is_gift' => 'boolean']);
        try {
            $quote = app(CheckoutQuote::class)->execute($session->current(), $data);

            return response()->json(array_intersect_key($quote, array_flip(['subtotal', 'discount', 'shipping', 'gift_fee', 'tax', 'tax_included', 'total'])));
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function store(CheckoutRequest $request, CartSession $session, PlaceOrder $place): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('payment_proof')) {
            $data['payment_proof'] = $request->file('payment_proof')->store('payment-proofs', 'local');
        }
        $order = $place->execute($session->current(), $data);
        $ids = (array) session('placed_orders', []);
        $ids[] = $order->id;
        session(['placed_orders' => array_slice(array_unique($ids), -20)]);
        session()->forget('checkout_key');

        return redirect()->route('checkout.success', ['order' => $order->number]);
    }

    public function success(string $order): View
    {
        $record = Order::where('number', $order)->with('items')->firstOrFail();
        abort_unless(in_array($record->id, (array) session('placed_orders', []), true) || ($record->user_id && Auth::guard('web')->id() === $record->user_id), 403);

        return view('themes.default.success', ['order' => $record]);
    }
}
