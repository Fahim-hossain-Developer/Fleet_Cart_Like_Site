<?php

namespace App\Livewire;

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Cart\Actions\CartSession;
use App\Domain\Cart\Actions\QuoteCart;
use App\Domain\Marketing\Actions\ValidateCoupon;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Shipping\Actions\ShippingResolver;
use App\Domain\Shipping\Models\Location;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CartPage extends Component
{
    public string $coupon = '';

    public string $district = '';

    public function quantity(int $id, int $qty): void
    {
        app(CartManager::class)->quantity(app(CartSession::class)->current(), $id, $qty);
    }

    public function remove(int $id): void
    {
        app(CartManager::class)->remove(app(CartSession::class)->current(), $id);
    }

    public function save(int $id, bool $saved): void
    {
        app(CartManager::class)->save(app(CartSession::class)->current(), $id, $saved);
    }

    public function applyCoupon(): void
    {
        $this->validate(['coupon' => 'required|string|max:50']);
        $cart = app(CartSession::class)->current();
        $coupon = Coupon::where('code', strtoupper(trim($this->coupon)))->first();
        if (! $coupon) {
            throw ValidationException::withMessages(['coupon' => __('checkout.invalid_coupon')]);
        }
        $lines = app(QuoteCart::class)->execute($cart);
        app(ValidateCoupon::class)->execute($coupon, array_sum(array_column($lines, 'subtotal')));
        $cart->update(['coupon_id' => $coupon->id]);
    }

    public function clearCoupon(): void
    {
        app(CartSession::class)->current()->update(['coupon_id' => null]);
        $this->coupon = '';
    }

    public function render(): View
    {
        $cart = app(CartSession::class)->current();
        $lines = [];
        $issue = null;
        $promotion = ['discount' => 0, 'free_shipping' => false];
        try {
            $lines = app(QuoteCart::class)->execute($cart);
            $promotion = app(ValidateCoupon::class)->execute($cart->coupon_id ? Coupon::find($cart->coupon_id) : null, array_sum(array_column($lines, 'subtotal')));
        } catch (ValidationException $e) {
            $issue = collect($e->errors())->flatten()->first();
        }
        $subtotal = array_sum(array_column($lines, 'subtotal'));

        return view('livewire.cart-page', ['cart' => $cart, 'lines' => $lines, 'issue' => $issue, 'subtotal' => $subtotal, 'promotion' => $promotion, 'districts' => Location::where('type', 'district')->get(), 'shipping' => $this->district ? app(ShippingResolver::class)->methods('BD', (int) $this->district, $subtotal - $promotion['discount'], array_sum(array_column($lines, 'weight')), $promotion['free_shipping']) : []]);
    }
}
