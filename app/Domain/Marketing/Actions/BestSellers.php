<?php

namespace App\Domain\Marketing\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class BestSellers
{
    /** @return list<int> */
    public function ids(): array
    {
        return Cache::remember('catalog.real-best-sellers', 60, fn () => DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.placed_at', '>=', now()->subDays(30))->where('orders.payment_environment', 'live')->whereIn('orders.status', ['delivered', 'completed'])->where('orders.payment_status', 'paid')->where('orders.total', '>', 0)->whereColumn('orders.paid_amount', '>=', 'orders.total')->where('orders.refunded_amount', 0)->select('order_items.product_id')->groupBy('order_items.product_id')->orderByRaw('SUM(order_items.qty) DESC')->orderBy('order_items.product_id')->limit(10)->pluck('order_items.product_id')->map(fn ($id) => (int) $id)->all());
    }
}
