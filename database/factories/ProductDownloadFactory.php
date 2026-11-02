<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\ProductDownload;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductDownload> */
class ProductDownloadFactory extends Factory
{
    protected $model = ProductDownload::class;

    public function definition(): array
    {
        return ['product_id' => ProductFactory::new(), 'file' => 'product-downloads/example.txt', 'limit' => 3, 'expiry_days' => 7];
    }
}
