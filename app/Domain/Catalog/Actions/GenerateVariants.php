<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GenerateVariants
{
    public function execute(Product $product): int
    {
        return DB::transaction(function () use ($product): int {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $groups = $product->attributeValues()->get()->groupBy('attribute_id');
            if ($groups->isEmpty()) {
                throw ValidationException::withMessages(['attributes' => __('catalog.choose_attributes')]);
            }
            $combinations = [[]];
            foreach ($groups as $attribute => $values) {
                $next = [];
                foreach ($combinations as $combination) {
                    foreach ($values as $value) {
                        $next[] = $combination + [(string) $attribute => (string) $value->id];
                    }
                } $combinations = $next;
                if (count($combinations) > 200) {
                    throw ValidationException::withMessages(['attributes' => __('catalog.too_many_variants')]);
                }
            }
            $created = 0;
            foreach ($combinations as $attributes) {
                ksort($attributes);
                $found = $product->variants->contains(function (ProductVariant $v) use ($attributes): bool {
                    $stored = $v->attributes;
                    ksort($stored);

                    return $stored === $attributes;
                });
                if ($found) {
                    continue;
                }
                $i = $product->variants()->count() + 1;
                $sku = substr($product->sku, 0, 220).'-v'.$i;
                while (ProductVariant::where('sku', $sku)->exists() || Product::withTrashed()->where('sku', $sku)->exists()) {
                    $sku = substr($product->sku, 0, 220).'-v'.++$i;
                }
                $product->variants()->create(['sku' => $sku, 'attributes' => $attributes, 'price' => $product->price, 'qty' => 0]);
                $created++;
            }

            return $created;
        });
    }
}
