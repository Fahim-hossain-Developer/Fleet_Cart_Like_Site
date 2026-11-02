<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Actions\StorefrontOverlay;
use App\Domain\Orders\Models\Order;
use Illuminate\Support\Facades\Cache;

final class StorefrontControls
{
    /** @return array<string,mixed> */
    public function floating(): array
    {
        $r = request();
        $scope = app(StorefrontOverlay::class);
        $c = store_setting('floating_controls', []);
        if (! $scope->allowed($r) || ! $scope->matches($c['paths'] ?? null, $r) || (! empty($c['exclude']) && $scope->matches($c['exclude'], $r))) {
            return [];
        }
        $links = [];
        foreach (['whatsapp', 'call', 'messenger'] as $type) {
            $d = $c[$type] ?? [];
            if (empty($d['enabled'])) {
                continue;
            }
            if ($type === 'messenger') {
                if (! preg_match('/^[a-zA-Z0-9.]{3,100}$/D', $d['page'] ?? '')) {
                    continue;
                } $url = 'https://m.me/'.$d['page'];
            } else {
                if (! preg_match('/^\+[1-9][0-9]{7,14}$/D', $d['phone'] ?? '')) {
                    continue;
                }
                $message = (string) ($d['message'] ?? '');
                if ($r->route()?->getName() === 'product') {
                    $product = Product::published()->where('slug', $r->route('slug'))->first();
                    if ($product) {
                        $message = trim($message.' '.$product->name.' '.route('product', $product->slug));
                    }
                }
                $url = $type === 'call' ? 'tel:'.$d['phone'] : 'https://wa.me/'.ltrim($d['phone'], '+').'?text='.rawurlencode($message);
            }
            $links[] = ['type' => $type, 'url' => $url];
        }
        $chat = $c['chat'] ?? [];
        $chatUrl = ! empty($chat['enabled']) && preg_match('/^[a-f0-9]{24}$/D', $chat['property'] ?? '') && preg_match('/^[a-zA-Z0-9]{1,30}$/D', $chat['widget'] ?? '') ? 'https://embed.tawk.to/'.$chat['property'].'/'.$chat['widget'] : null;

        return ['links' => $links, 'position' => ($c['position'] ?? 'right') === 'left' ? 'left' : 'right', 'top' => ! empty($c['back_to_top']), 'chat' => $chatUrl];
    }

    /** @return array{items:array<int,array<string,mixed>>,frequency:int} */
    public function proof(): array
    {
        $c = store_setting('social_proof', []);
        $scope = app(StorefrontOverlay::class);
        $r = request();
        if (empty($c['enabled']) || ! $scope->allowed($r) || (! empty($c['exclude']) && $scope->matches($c['exclude'], $r))) {
            return ['items' => [], 'frequency' => 60];
        }
        $locale = app()->getLocale();
        $items = Cache::remember('store.social-proof.'.$locale, 60, function () use ($locale) {
            $result = [];
            $orders = Order::whereIn('status', ['delivered', 'completed'])->where('payment_status', 'paid')->where('payment_environment', 'live')->where('total', '>', 0)->whereColumn('paid_amount', '>=', 'total')->where('refunded_amount', 0)->whereBetween('placed_at', [now()->subDay(), now()])->with('items')->latest('placed_at')->limit(12)->get();
            foreach ($orders as $o) {
                $city = $o->shipping_address['district'][$locale] ?? $o->shipping_address['district']['en'] ?? null;
                if (! is_string($city) || mb_strlen($city) > 100) {
                    continue;
                }
                foreach ($o->items as $item) {
                    $p = Product::published()->find($item->product_id);
                    if (! $p) {
                        continue;
                    }
                    $result[] = ['text' => __('marketing.real_purchase', ['city' => $city, 'product' => $p->name, 'time' => $o->placed_at->diffForHumans()]), 'url' => route('product', $p->slug)];
                    break;
                }
            }

            return $result;
        });

        return ['items' => $items, 'frequency' => min(3600, max(30, (int) ($c['frequency'] ?? 60)))];
    }
}
