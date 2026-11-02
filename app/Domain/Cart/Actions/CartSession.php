<?php

namespace App\Domain\Cart\Actions;

use App\Domain\Cart\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CartSession
{
    public function current(): Cart
    {
        $token = session('cart_token');
        if (! is_string($token) || strlen($token) !== 64) {
            $token = Str::random(64);
            session(['cart_token' => $token]);
        }
        $guest = Cart::where('token', $token)->first();
        if (Auth::guard('web')->check()) {
            return DB::transaction(function () use ($guest): Cart {
                $user = User::query()->lockForUpdate()->findOrFail(Auth::guard('web')->id());
                $cart = Cart::firstOrCreate(['user_id' => $user->id], ['token' => Str::random(64)]);
                if ($guest && ! $guest->user_id && $guest->id !== $cart->id) {
                    foreach ($guest->items as $item) {
                        $existing = $cart->items()->where('line_key', $item->line_key)->first();
                        if ($existing) {
                            $existing->update(['qty' => min(100, $existing->qty + $item->qty)]);
                        } else {
                            $copy = $item->replicate();
                            $cart->items()->save($copy);
                        }
                    }
                    if (! $cart->coupon_id) {
                        $cart->update(['coupon_id' => $guest->coupon_id]);
                    }$guest->delete();
                }
                session(['cart_token' => $cart->token]);

                return $cart;
            });
        }
        if ($guest && ! $guest->user_id) {
            return $guest;
        }
        $token = Str::random(64);
        session(['cart_token' => $token]);

        return Cart::create(['token' => $token]);
    }
}
