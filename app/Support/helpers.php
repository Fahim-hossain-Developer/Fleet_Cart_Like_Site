<?php

use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Settings\Actions\CurrencyDisplay;
use App\Domain\Settings\Actions\Settings;

function store_setting(string $key, mixed $default = null): mixed
{
    return app(Settings::class)->get($key, $default);
}

function catalog_money(int $minor): string
{
    return app(CurrencyDisplay::class)->format($minor);
}
function catalog_variant_label(ProductVariant $variant): string
{
    $parts = [];
    foreach ($variant->attributes as $key => $value) {
        $attribute = is_numeric($key) ? Attribute::find($key) : null;
        $option = $attribute && is_numeric($value) ? $attribute->values()->find($value) : null;
        $parts[] = ($attribute->name ?? (string) $key).': '.($option->name ?? (string) $value);
    }

    return implode(' / ', $parts);
}
