<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Marketing\Models\FlashSaleItem;
use Illuminate\Support\Facades\DB;

final class FlashPricing
{
    /** @return array{price:int,item:FlashSaleItem|null} */
    public function quote(Product $p, ?ProductVariant $v, int $base, int $qty = 1, bool $lock = false, int $reserved = 0): array
    {
        $item = FlashSaleItem::where('product_id', $p->id)->where('variant_key', $v->id ?? 0)->where('is_active', true)->where('price', '<', $base)->whereRaw('quota - sold >= ?', [$qty + $reserved])->whereHas('sale', fn ($q) => $q->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>', now()))->orderBy('price')->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->first();

        return ['price' => $item->price ?? $base, 'item' => $item];
    }

    public function variant(Product $p, ProductVariant $v): int
    {
        return $this->quote($p, $v, $v->special_price ?? $v->price)['price'];
    }

    public function active(Product $p): bool
    {
        if ($p->type === 'variable') {
            return $p->variants->where('is_active', true)->contains(fn ($v) => $this->quote($p, $v, $v->special_price ?? $v->price)['item'] !== null);
        }

        return $this->quote($p, null, $p->basePrice())['item'] !== null;
    }

    /** SQL fragment with only trusted integer identifiers and a server-quoted timestamp. */
    public function expression(string $base, string $variantExpression = '0'): string
    {
        $now = DB::connection()->getPdo()->quote(now()->toDateTimeString());
        $flash = "(SELECT MIN(flash_sale_items.price) FROM flash_sale_items INNER JOIN flash_sales ON flash_sales.id = flash_sale_items.flash_sale_id WHERE flash_sale_items.product_id = products.id AND flash_sale_items.variant_key = {$variantExpression} AND flash_sale_items.is_active = 1 AND flash_sale_items.sold < flash_sale_items.quota AND flash_sales.is_active = 1 AND flash_sales.starts_at <= {$now} AND flash_sales.ends_at > {$now})";

        return "CASE WHEN {$flash} IS NOT NULL AND {$flash} < ({$base}) THEN {$flash} ELSE ({$base}) END";
    }
}
