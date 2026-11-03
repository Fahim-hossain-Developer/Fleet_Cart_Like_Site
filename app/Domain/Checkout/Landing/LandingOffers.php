<?php

namespace App\Domain\Checkout\Landing;

use App\Domain\Cart\Actions\CartManager;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductDownload;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Checkout\Actions\PriceCalculator;
use App\Domain\Checkout\Models\TaxRate;
use App\Domain\Content\Models\ProductPackage;
use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Actions\OrderInventory;
use App\Domain\Orders\Models\DownloadEntitlement;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\PendingOperations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One additional line, fifteen minutes, original receipts and shipping preserved. */
final class LandingOffers
{
    public function owner(Order $o): void
    {
        abort_unless(in_array($o->id, (array) session('placed_orders', []), true) || ($o->user_id && auth('web')->id() === $o->user_id), 403);
    }

    private function eligible(Order $o): void
    {
        if (! $o->landing_page_id || $o->placed_at->copy()->addMinutes(15)->isPast() || ! in_array($o->status, ['pending', 'confirmed', 'processing', 'on-hold']) || $o->fulfillment_status !== 'unfulfilled' || $o->shipments()->exists() || $o->returns()->exists() || $o->refunded_amount || $o->refunds()->exists() || (app()->isProduction() && $o->payment_environment === 'sandbox')) {
            $this->fail();
        }
        app(PendingOperations::class)->mutable($o);
        app(PendingOperations::class)->refundFree($o);
    }

    private function fail(): never
    {
        throw ValidationException::withMessages(['offer' => __('landing.offer_unavailable')]);
    }

    /** @return array<string,mixed>|null */
    public function current(Order $o): ?array
    {
        $this->owner($o);
        try {
            return DB::transaction(function () use ($o) {
                $o = Order::lockForUpdate()->findOrFail($o->id);

                return $this->quote($o);
            }, 3);
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * Caller owns order lock.
     *
     * @return array<string,mixed>
     */
    private function quote(Order $o): array
    {
        $this->eligible($o);
        $decision = DB::table('landing_offer_decisions')->where('order_id', $o->id)->first();
        $stage = $decision->stage ?? 'upsell';
        if (! in_array($stage, ['upsell', 'downsell'])) {
            $this->fail();
        }
        $id = $o->landing_snapshot['form_config'][$stage.'_package_id'] ?? null;
        $p = ProductPackage::where('landing_page_id', $o->landing_page_id)->where('role', $stage)->where('is_active', true)->lockForUpdate()->find($id);
        if (! $p) {
            $this->fail();
        }
        $product = Product::published()->lockForUpdate()->find($p->product_id);
        if (! $product || $product->options()->where('required', true)->exists()) {
            $this->fail();
        }
        $variant = $p->variant_id ? ProductVariant::where('product_id', $product->id)->where('is_active', true)->lockForUpdate()->find($p->variant_id) : null;
        if (($product->type === 'variable') !== ($variant !== null)) {
            $this->fail();
        }
        $existing = (int) $o->items()->where('product_id', $product->id)->sum('qty');
        if ($product->max_per_order && $existing + $p->qty > $product->max_per_order) {
            $this->fail();
        }
        $owed = (int) $o->items()->where('product_id', $product->id)->where('variant_id', $p->variant_id)->selectRaw('COALESCE(SUM(qty-stock_deducted),0) AS owed')->value('owed');
        app(CartManager::class)->assertStock($product, $variant, $p->qty + $owed);
        $meta = $o->checkout_meta ?? [];
        $rate = TaxRate::where('tax_class_id', $product->tax_class_id)->where('country', $meta['country'] ?? 'BD')->where('is_active', true)->where(fn ($q) => $q->whereNull('district_id')->orWhere('district_id', (int) ($meta['district_id'] ?? 0)))->orderByDesc('district_id')->first();
        $line = ['product_id' => $product->id, 'variant_id' => $variant?->id, 'name' => $product->name, 'sku' => $variant->sku ?? $product->sku, 'qty' => $p->qty, 'unit_price' => intdiv($p->price, $p->qty), 'subtotal' => $p->price, 'tax_bps' => $rate->rate_bps ?? 0, 'tax_inclusive' => $rate->inclusive ?? false, 'options' => ['values' => [], 'labels' => [['label' => __('landing.package'), 'value' => $p->label[app()->getLocale()] ?? $p->label['en']]]], 'package_snapshot' => ['id' => $p->id, 'label' => $p->label, 'qty' => $p->qty, 'units' => 1, 'package_price' => $p->price, 'charged_subtotal' => $p->price, 'role' => $stage, 'free_shipping' => true]];
        $totals = app(PriceCalculator::class)->calculate([$line], 0, 0);
        $line = $totals['lines'][0];
        if ($o->total + $totals['total'] > 100000000000) {
            $this->fail();
        }
        $hash = hash_hmac('sha256', json_encode([$o->id, $stage, $p->id, $o->total, $o->paid_amount, $line], JSON_THROW_ON_ERROR), config('app.key'));

        return ['stage' => $stage, 'package_id' => $p->id, 'label' => $p->label[app()->getLocale()] ?? $p->label['en'], 'amount' => $totals['total'], 'hash' => $hash, 'line' => $line, 'tax_included' => $totals['tax_included'], 'expires_at' => $o->placed_at->copy()->addMinutes(15)->toIso8601String()];
    }

    public function decide(Order $o, string $stage, bool $accept, ?string $hash): void
    {
        $this->owner($o);
        DB::transaction(function () use ($o, $stage, $accept, $hash) {
            $o = Order::lockForUpdate()->findOrFail($o->id);
            $old = DB::table('landing_offer_decisions')->where('order_id', $o->id)->first();
            if ($old && $old->stage === 'accepted' && $accept) {
                return;
            }
            if ($old && $old->stage === 'closed' && ! $accept) {
                return;
            }
            $offer = $this->quote($o);
            if ($stage !== $offer['stage']) {
                $this->fail();
            }
            if ($accept && (! $hash || ! hash_equals($offer['hash'], $hash))) {
                $this->fail();
            }
            $next = $accept ? 'accepted' : ($stage === 'upsell' ? 'downsell' : 'closed');
            if ($accept) {
                $line = $offer['line'];
                $item = $o->items()->create($line + ['pricing_qty' => $line['qty'], 'pricing_subtotal' => $line['subtotal']]);
                $newTotal = $o->total + $line['total'];
                $o->update(['subtotal' => $o->subtotal + $line['subtotal'], 'tax' => $o->tax + $line['tax'], 'tax_included' => $o->tax_included + $offer['tax_included'], 'total' => $newTotal, 'display_total' => (int) bcdiv(bcmul((string) $newTotal, (string) $o->display_rate_ppm, 0), '1000000', 0), 'payment_status' => $o->paid_amount >= $newTotal ? 'paid' : ($o->paid_amount > 0 ? 'partial' : 'unpaid')]);
                if (($o->stock_policy === 'payment' && $o->payment_status === 'paid') || $o->stock_policy === 'placement' || in_array($o->status, ['confirmed', 'processing']) && $o->stock_policy === 'confirmation') {
                    app(OrderInventory::class)->deduct($o);
                }
                foreach (ProductDownload::where('product_id', $line['product_id'])->get() as $download) {
                    DownloadEntitlement::create(['order_item_id' => $item->id, 'product_download_id' => $download->id, 'label' => $download->label ?? $item->name, 'file' => $download->file, 'external_url' => $download->external_url, 'enabled_at' => $o->payment_status === 'paid' ? now() : null, 'expires_at' => $o->payment_status === 'paid' && $download->expiry_days ? now()->addDays($download->expiry_days) : null, 'limit' => $download->limit, 'expiry_days' => $download->expiry_days]);
                }
                app(ManageOrder::class)->event($o, 'landing_offer', __('landing.offer_added'), true);
            }
            DB::table('landing_offer_decisions')->updateOrInsert(['order_id' => $o->id], ['stage' => $next, 'package_id' => $accept ? $offer['package_id'] : null, 'decided_at' => now(), 'created_at' => $old->created_at ?? now(), 'updated_at' => now()]);
        }, 3);
    }
}
