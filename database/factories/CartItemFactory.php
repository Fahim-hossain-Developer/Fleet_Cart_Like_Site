<?php

namespace Database\Factories;

use App\Domain\Cart\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CartItem> */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return ['cart_id' => CartFactory::new(), 'product_id' => ProductFactory::new(), 'line_key' => Str::random(64), 'qty' => 1, 'options' => []];
    }
}
