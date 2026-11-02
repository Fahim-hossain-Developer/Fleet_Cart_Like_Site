<?php

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Cart\Actions\CartSession;
use App\Domain\Catalog\Models\Product;
use App\Domain\Settings\Models\Currency;
use App\Domain\Shipping\Models\Location;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingZone;
use Illuminate\Support\Str;

function checkoutFixture(int $stock = 10): array
{
    $division = Location::create(['code' => Str::random(10), 'country' => 'BD', 'type' => 'division', 'name' => ['en' => 'Dhaka', 'bn' => 'ঢাকা']]);
    $district = Location::create(['code' => Str::random(10), 'country' => 'BD', 'type' => 'district', 'parent_id' => $division->id, 'name' => ['en' => 'Dhaka', 'bn' => 'ঢাকা']]);
    $upazila = Location::create(['code' => Str::random(10), 'country' => 'BD', 'type' => 'upazila', 'parent_id' => $district->id, 'name' => ['en' => 'Savar', 'bn' => 'সাভার']]);
    $zone = ShippingZone::create(['name' => ['en' => 'Local', 'bn' => 'স্থানীয়'], 'country' => 'BD', 'district_ids' => [$district->id], 'is_active' => true]);
    $method = ShippingMethod::create(['shipping_zone_id' => $zone->id, 'name' => ['en' => 'Standard', 'bn' => 'সাধারণ'], 'amount' => 8000, 'is_active' => true, 'type' => 'flat']);
    Currency::firstOrCreate(['code' => 'BDT'], ['symbol' => '৳', 'rate_ppm' => 1000000, 'decimals' => 2, 'is_active' => true]);
    $product = Product::factory()->create(['price' => 100000, 'qty' => $stock]);
    $cart = app(CartSession::class)->current();
    app(CartManager::class)->add($cart, $product->id, null, 1);
    $data = ['name' => 'Test customer', 'phone' => '01712345678', 'email' => 'customer@example.test', 'country' => 'BD', 'division_id' => $division->id, 'district_id' => $district->id, 'upazila_id' => $upazila->id, 'address' => 'Test address 123', 'shipping_method_id' => $method->id, 'payment_method' => 'cod', 'terms' => '1', 'idempotency_key' => (string) Str::uuid(), 'is_gift' => false, 'hide_prices' => false, 'create_account' => false];

    return compact('division', 'district', 'upazila', 'zone', 'method', 'product', 'cart', 'data');
}
