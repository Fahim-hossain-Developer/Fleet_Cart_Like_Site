<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\FlashSale;
use App\Domain\Marketing\Models\FlashSaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlashSaleItem> */
class FlashSaleItemFactory extends Factory
{
    protected $model = FlashSaleItem::class;

    public function definition(): array
    {
        return ['flash_sale_id' => FlashSale::factory(), 'product_id' => Product::factory(), 'price' => 50000, 'quota' => 10, 'sold' => 0];
    }
}
