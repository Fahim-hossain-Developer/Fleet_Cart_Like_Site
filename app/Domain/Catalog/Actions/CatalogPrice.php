<?php

namespace App\Domain\Catalog\Actions;

use Illuminate\Support\Facades\DB;

final class CatalogPrice
{
    /** A server-generated expression only: no request text enters this SQL. */
    public function expression(): string
    {
        $date = DB::connection()->getPdo()->quote(now()->toDateTimeString());
        $base = "CASE WHEN products.special_price IS NOT NULL AND (products.special_from IS NULL OR products.special_from <= {$date}) AND (products.special_to IS NULL OR products.special_to >= {$date}) THEN products.special_price ELSE products.price END";

        return "CASE WHEN products.type = 'variable' THEN COALESCE((SELECT MIN(COALESCE(product_variants.special_price,product_variants.price)) FROM product_variants WHERE product_variants.product_id = products.id AND product_variants.is_active = 1), {$base}) ELSE {$base} END";
    }
}
