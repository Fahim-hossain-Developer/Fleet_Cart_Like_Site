<?php

namespace App\Jobs;

use App\Domain\Customers\Actions\WatchProduct;
use App\Domain\Customers\Models\ProductWatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CheckProductWatches implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $productId = null) {}

    public function handle(): void
    {
        ProductWatch::where('is_active', true)->when($this->productId, fn ($q, $id) => $q->where('product_id', $id))->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $w) {
                app(WatchProduct::class)->check($w->id);
            }
        });
    }
}
