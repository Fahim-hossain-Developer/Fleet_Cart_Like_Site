<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\FlashAllocation;
use App\Domain\Marketing\Models\FlashSaleItem;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlashAllocation> */
class FlashAllocationFactory extends Factory
{
    protected $model = FlashAllocation::class;

    public function definition(): array
    {
        return ['order_item_id' => OrderItem::factory(), 'flash_sale_item_id' => FlashSaleItem::factory(), 'qty' => 1, 'released' => 0];
    }
}
