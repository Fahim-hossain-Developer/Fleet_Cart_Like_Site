<?php

namespace Database\Factories;

use App\Domain\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return ['order_id' => OrderFactory::new(), 'product_id' => ProductFactory::new(), 'name' => 'Sample', 'sku' => 'SAMPLE', 'options' => [], 'qty' => 1, 'unit_price' => 100, 'subtotal' => 100, 'discount' => 0, 'tax' => 0, 'total' => 100];
    }
}
