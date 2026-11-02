<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Badge;
use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Actions\BestSellers;
use App\Domain\Marketing\Actions\FlashPricing;

final class ProductBadges
{
    /** @return list<array{label:string,color:string,priority:int}> */
    public function for(Product $p): array
    {
        $badges = [];
        if (in_array($p->id, app(BestSellers::class)->ids(), true)) {
            $badges[] = ['label' => __('marketing.best_seller'), 'color' => '#5b21b6', 'priority' => 25];
        }
        if (app(FlashPricing::class)->active($p)) {
            $badges[] = ['label' => __('marketing.flash_sale'), 'color' => '#9f1239', 'priority' => 5];
        }
        if (! $p->available()) {
            $badges[] = ['label' => __('catalog.out_of_stock'), 'color' => '#44403c', 'priority' => 0];
        }
        if ($p->currentPrice() < $p->price && $p->price > 0) {
            $badges[] = ['label' => __('catalog.sale').' -'.intdiv(($p->price - $p->currentPrice()) * 100, $p->price).'%', 'color' => '#9f1239', 'priority' => 10];
        }
        if (($p->new_until && $p->new_until->isFuture()) || (! $p->new_until && $p->created_at->gt(now()->subDays((int) store_setting('catalog_new_days', config('catalog.new_days')))))) {
            $badges[] = ['label' => __('catalog.new'), 'color' => '#065f46', 'priority' => 20];
        }
        if ($p->is_preorder) {
            $badges[] = ['label' => __('catalog.preorder'), 'color' => '#1e40af', 'priority' => 15];
        }
        if ($p->manage_stock && $p->qty > 0 && $p->qty <= $p->low_stock_at && $p->type !== 'variable') {
            $badges[] = ['label' => __('catalog.low_stock'), 'color' => '#92400e', 'priority' => 30];
        }
        if ($p->free_shipping) {
            $badges[] = ['label' => __('catalog.free_shipping'), 'color' => '#065f46', 'priority' => 40];
        }
        $categoryIds = $p->categories->modelKeys();
        $manual = Badge::query()->where('is_active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))->where(function ($q) use ($p, $categoryIds): void {
            $q->whereHas('products', fn ($q) => $q->where('products.id', $p->id))->orWhereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds));
        })->get();
        foreach ($manual as $badge) {
            $badges[] = ['label' => trim(($badge->icon ?? '').' '.$badge->label), 'color' => preg_match('/^#[0-9a-fA-F]{6}$/D', $badge->color) ? $badge->color : '#065f46', 'priority' => $badge->priority];
        }
        usort($badges, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return array_slice($badges, 0, max(0, min(10, (int) store_setting('catalog_max_badges', config('catalog.max_badges')))));
    }
}
