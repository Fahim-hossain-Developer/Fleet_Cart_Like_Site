<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\ProductWatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class WatchProduct
{
    public function set(User $u, Product $p, string $kind, bool $enabled): void
    {
        abort_if($u->is_blocked, 403);
        abort_unless(in_array($kind, ['stock', 'price'], true), 422);
        abort_unless($p->status === 'active' && $p->visibility === 'visible', 404);
        DB::transaction(function () use ($u, $p, $kind, $enabled) {
            User::lockForUpdate()->findOrFail($u->id);
            abort_if(ProductWatch::where('user_id', $u->id)->where('is_active', true)->count() >= 100 && ! ProductWatch::where('user_id', $u->id)->where('product_id', $p->id)->where('kind', $kind)->exists(), 422);
            ProductWatch::updateOrCreate(['user_id' => $u->id, 'product_id' => $p->id, 'kind' => $kind], ['is_active' => $enabled, 'last_price' => $p->displayPrice(), 'last_available' => $p->available()]);
        });
    }

    public function check(int $id): void
    {
        DB::transaction(function () use ($id) {
            $w = ProductWatch::lockForUpdate()->find($id);
            if (! $w || ! $w->is_active) {
                return;
            }$p = Product::published()->with('variants')->find($w->product_id);
            $u = User::find($w->user_id);
            if (! $p || ! $u || $u->is_blocked) {
                return;
            }$available = $p->available();
            $price = $p->displayPrice();
            $trigger = $w->kind === 'stock' ? (! $w->last_available && $available) : ($price < $w->last_price);
            if ($trigger) {
                $w->sequence++;
                $topic = $w->kind === 'stock' ? 'stock' : 'price';
                app(PublishNotice::class)->execute($u, $topic, 'marketing.notice_'.$topic, ['product' => $p->name], route('product', $p->slug, false), 'watch:'.$w->id.':'.$w->sequence);
                $w->notified_at = now();
            }
            $w->last_available = $available;
            $w->last_price = $price;
            $w->save();
        });
    }
}
