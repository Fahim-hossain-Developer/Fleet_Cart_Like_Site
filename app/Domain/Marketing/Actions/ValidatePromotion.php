<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Marketing\Models\CartRule;
use App\Domain\Marketing\Models\FlashAllocation;
use App\Domain\Marketing\Models\FlashSale;
use App\Domain\Marketing\Models\FlashSaleItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class ValidatePromotion
{
    public function execute(Model $m): void
    {
        if ($m instanceof FlashSale) {
            validator($m->getAttributes(), ['starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at'])->validate();
        }
        if ($m instanceof FlashSaleItem) {
            $p = Product::findOrFail($m->product_id);
            if (($p->type === 'variable') !== ($m->variant_id !== null)) {
                throw ValidationException::withMessages(['variant_id' => __('marketing.invalid_promotion')]);
            }
            if ($m->exists && ($m->isDirty('product_id') || $m->isDirty('variant_id')) && FlashAllocation::where('flash_sale_item_id', $m->id)->exists()) {
                throw ValidationException::withMessages(['product_id' => __('marketing.invalid_promotion')]);
            }
            validator($m->getAttributes(), ['price' => 'required|integer|min:0|max:100000000000', 'quota' => 'required|integer|min:1|max:1000000', 'sold' => 'integer|min:0|lte:quota'])->validate();
            if ($m->variant_id && ! ProductVariant::where('product_id', $m->product_id)->whereKey($m->variant_id)->exists()) {
                throw ValidationException::withMessages(['variant_id' => __('marketing.invalid_promotion')]);
            }
            $m->variant_key = $m->variant_id ?? 0;
        }
        if ($m instanceof CartRule) {
            validator($m->getAttributes(), ['minimum' => 'required|integer|min:0|max:100000000000', 'minimum_qty' => 'required|integer|min:1|max:10000', 'priority' => 'required|integer|min:0|max:100000', 'type' => 'required|in:fixed,percent,free_shipping', 'value' => 'required|integer|min:0|max:100000000000', 'max_discount' => 'nullable|integer|min:0|max:100000000000', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at'])->validate();
        }
    }
}
