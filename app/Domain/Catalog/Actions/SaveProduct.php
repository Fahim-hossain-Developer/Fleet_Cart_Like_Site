<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SaveProduct
{
    /** @param array<string,mixed> $data */
    public function execute(array $data, ?Product $product = null): Product
    {
        Validator::make($data, ['sku' => ['required', 'max:220', Rule::unique('products', 'sku')->ignore($product?->id), Rule::unique('product_variants', 'sku')], 'slug' => ['required', 'max:220', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique('products', 'slug')->ignore($product?->id)], 'name.en' => 'required|string|max:255', 'name.bn' => 'nullable|string|max:255', 'type' => 'required|in:simple,variable,digital', 'status' => 'required|in:draft,active', 'price' => 'required|integer|min:0|max:100000000000', 'special_price' => 'nullable|integer|min:0|lte:price', 'qty' => 'required|integer|min:0|max:1000000', 'special_to' => 'nullable|date|after_or_equal:special_from'])->validate();
        $product ??= new Product;
        $product->fill($data);
        $product->save();

        return $product;
    }
}
