<?php

namespace Database\Factories;

use App\Domain\Orders\Models\DownloadEntitlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DownloadEntitlement> */
class DownloadEntitlementFactory extends Factory
{
    protected $model = DownloadEntitlement::class;

    public function definition(): array
    {
        return ['order_item_id' => OrderItemFactory::new(), 'product_download_id' => ProductDownloadFactory::new(), 'label' => 'Digital purchase', 'file' => 'product-downloads/sample.txt', 'limit' => 3, 'expiry_days' => 30];
    }
}
