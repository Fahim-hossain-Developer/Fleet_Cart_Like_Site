<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Cart\Actions\CartSession;
use App\Domain\Cart\Actions\QuoteCart;
use App\Domain\Cart\Models\Cart;
use App\Domain\Catalog\Models\Product;
use App\Domain\Marketing\Models\TrackingConsent;
use App\Domain\Marketing\Models\TrackingEvent;
use App\Domain\Orders\Models\Order;
use App\Jobs\DeliverTrackingEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class Tracking
{
    public function consent(): ?TrackingConsent
    {
        if (request()->has('theme_preview') || ! store_setting('tracking_enabled', false)) {
            return null;
        }

        return TrackingConsent::where('uuid', session('tracking_consent'))->whereNull('revoked_at')->where('expires_at', '>', now())->first();
    }

    public function choose(bool $accepted): void
    {
        $old = TrackingConsent::where('uuid', session('tracking_consent'))->first();
        if ($old) {
            $old->update(['revoked_at' => now(), 'private_data' => null]);
        }session()->forget('tracking_consent');
        session(['tracking_choice' => $accepted]);
        if (! $accepted || ! store_setting('tracking_enabled', false)) {
            return;
        }
        $c = TrackingConsent::create(['uuid' => (string) Str::uuid(), 'private_data' => ['ip' => request()->ip(), 'agent' => mb_substr(request()->userAgent() ?? '', 0, 500)], 'consented_at' => now(), 'expires_at' => now()->addDays(90)]);
        session(['tracking_consent' => $c->uuid]);
    }

    /** @param array<string,mixed> $data */
    private function record(TrackingConsent $consent, string $name, string $source, array $data, string $url): ?TrackingEvent
    {
        if ($consent->revoked_at || $consent->expires_at->isPast()) {
            return null;
        }
        $e = TrackingEvent::firstOrCreate(['source_key' => $source], ['event_id' => (string) Str::uuid(), 'tracking_consent_id' => $consent->id, 'name' => $name, 'data' => $data, 'source_url' => $url, 'occurred_at' => now(), 'status' => 'pending', 'attempts' => 0]);
        DB::afterCommit(function () use ($e) {
            try {
                DeliverTrackingEvent::dispatch($e->id);
            } catch (\Throwable $error) {
                Log::warning('Tracking event pending', ['event_id' => $e->event_id]);
            }
        });

        return $e;
    }

    public function product(Product $product, string $name = 'ViewContent', int $qty = 1, ?int $variantId = null): void
    {
        try {
            $c = $this->consent();
            if (! $c) {
                return;
            }
            $variant = $variantId ? $product->variants()->where('is_active', true)->find($variantId) : null;
            $price = $variant ? app(FlashPricing::class)->variant($product, $variant) : $product->displayPrice();
            $this->record($c, $name, 'interaction:'.Str::uuid(), ['content_ids' => [(string) $product->id], 'content_type' => 'product', 'currency' => config('checkout.base_currency'), 'value_minor' => $price * $qty, 'quantity' => $qty], route('product', $product->slug));
        } catch (\Throwable $e) {
            Log::warning('Tracking interaction unavailable');
        }
    }

    public function checkout(?Cart $cart = null, ?int $productId = null, ?string $sourceKey = null): void
    {
        try {
            $c = $this->consent();
            if (! $c) {
                return;
            }
            $ids = array_map(fn ($line) => (string) $line['product_id'], app(QuoteCart::class)->execute($cart ?? app(CartSession::class)->current()));
            if (! $ids && $productId && Product::published()->whereKey($productId)->exists()) {
                $ids = [(string) $productId];
            }
            $this->record($c, 'InitiateCheckout', 'checkout:'.$c->id.':'.hash('sha256', (string) ($sourceKey ?? session('checkout_key'))), ['currency' => config('checkout.base_currency'), 'content_ids' => $ids], $productId ? route('product', Product::findOrFail($productId)->slug) : route('shop'));
        } catch (\Throwable $e) {
            Log::warning('Checkout tracking pending');
        }
    }

    public function attach(Order $order): void
    {
        $c = $this->consent();
        if ($c) {
            $order->update(['tracking_consent_id' => $c->id]);
        }
    }

    public function purchase(int $orderId): void
    {
        $o = Order::find($orderId);
        if (! $o || ! $o->tracking_consent_id || ! store_setting('tracking_enabled', false) || $o->payment_environment !== 'live' || $o->payment_status !== 'paid' || $o->total <= 0 || $o->paid_amount < $o->total || $o->refunded_amount > 0 || in_array($o->status, ['cancelled', 'failed', 'refunded', 'returned'], true)) {
            return;
        }
        $c = TrackingConsent::find($o->tracking_consent_id);
        if (! $c) {
            return;
        }
        $this->record($c, 'Purchase', 'purchase:'.$o->id, ['content_ids' => $o->items()->pluck('product_id')->map(fn ($id) => (string) $id)->all(), 'content_type' => 'product', 'currency' => $o->currency, 'value_minor' => $o->total], route('home'));
    }

    /** @return array<string,mixed> */
    public function publicConfig(): array
    {
        $s = store_setting('tracking_ids', []);

        return array_intersect_key($s, array_flip(['ga4', 'gtm', 'meta', 'tiktok']));
    }

    /** @return array<int,array<string,mixed>> */
    public function browserEvents(): array
    {
        $c = $this->consent();
        if (! $c) {
            return [];
        }

        return DB::transaction(function () use ($c) {
            $rows = TrackingEvent::where('tracking_consent_id', $c->id)->whereNull('browser_claimed_at')->where('occurred_at', '>', now()->subDay())->orderBy('id')->limit(20)->lockForUpdate()->get();
            $result = [];
            foreach ($rows as $e) {
                $data = $e->data;
                if (isset($data['value_minor'])) {
                    $data['value'] = $data['value_minor'] / 100;
                    unset($data['value_minor']);
                }$result[] = ['id' => $e->event_id, 'name' => $e->name, 'data' => $data];
                $e->update(['browser_claimed_at' => now()]);
            }

            return $result;
        });
    }
}
