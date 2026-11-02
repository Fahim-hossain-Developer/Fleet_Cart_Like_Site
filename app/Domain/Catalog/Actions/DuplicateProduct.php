<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

final class DuplicateProduct
{
    public function execute(Product $source, ?bool $keepStock = null): Product
    {
        return DB::transaction(function () use ($source, $keepStock): Product {
            $source = Product::query()->lockForUpdate()->findOrFail($source->id);
            $copy = $source->replicate(['sku', 'slug', 'views', 'rating_avg', 'rating_count', 'deleted_at']);
            $n = 1;
            do {
                $suffix = '-copy-'.$n++;
                $slug = substr($source->slug, 0, 220).$suffix;
                $sku = substr($source->sku, 0, 220).$suffix;
            } while (Product::withTrashed()->where('slug', $slug)->orWhere('sku', $sku)->exists() || ProductVariant::where('sku', $sku)->exists());
            $keepStock ??= (bool) config('catalog.clone_keep_stock');
            $copy->fill(['sku' => $sku, 'slug' => $slug, 'status' => 'draft', 'qty' => $keepStock ? $source->qty : 0, 'views' => 0, 'rating_avg' => 0, 'rating_count' => 0]);
            $copy->save();
            foreach (['categories', 'tags', 'attributeValues', 'badges', 'hiddenTabs'] as $relation) {
                $copy->{$relation}()->sync($source->{$relation}()->allRelatedIds());
            }
            foreach ($source->variants as $variant) {
                $v = $variant->replicate();
                $base = substr($variant->sku, 0, 215).$suffix;
                $key = $base;
                $i = 1;
                while (ProductVariant::where('sku', $key)->exists() || Product::withTrashed()->where('sku', $key)->exists()) {
                    $key = $base.'-'.$i++;
                }
                $v->sku = $key;
                $v->qty = $keepStock ? $variant->qty : 0;
                $copy->variants()->save($v);
            }
            foreach ($source->options as $option) {
                $o = $copy->options()->save($option->replicate());
                foreach ($option->values as $value) {
                    $o->values()->save($value->replicate());
                }
            }
            foreach (['downloads', 'relations', 'tabs'] as $relation) {
                foreach ($source->{$relation} as $record) {
                    $copy->{$relation}()->save($record->replicate());
                }
            }
            foreach ($source->getMedia('images') as $media) {
                $media->copy($copy, 'images', 'public');
            }
            activity()->performedOn($copy)->event('duplicated')->withProperties(['source_id' => $source->id])->log('duplicated from #'.$source->id);

            return $copy->fresh();
        });
    }
}
