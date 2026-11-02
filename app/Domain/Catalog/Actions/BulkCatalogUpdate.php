<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

final class BulkCatalogUpdate
{
    /**
     * @param  iterable<Product>  $products
     * @param  array<string,mixed>  $data
     */
    public function execute(iterable $products, array $data): void
    {
        validator($data, ['price' => 'nullable|integer|min:0|max:100000000000', 'qty' => 'nullable|integer|min:0|max:1000000', 'categories' => 'array', 'categories.*' => 'integer|exists:categories,id'])->validate();
        DB::transaction(function () use ($products, $data): void {
            foreach ($products as $product) {
                $values = [];
                foreach (['price', 'qty'] as $key) {
                    if (isset($data[$key])) {
                        $values[$key] = (int) $data[$key];
                    }
                }
                if ($values) {
                    $product->update($values);
                }
                if (! empty($data['categories'])) {
                    $product->categories()->syncWithoutDetaching($data['categories']);
                    activity()->performedOn($product)->event('categories_assigned')->withProperties(['category_ids' => $data['categories']])->log('bulk category assignment');
                }
            }
        });
    }
}
