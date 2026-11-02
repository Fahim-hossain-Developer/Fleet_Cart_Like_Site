<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CatalogSearch
{
    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function apply(Builder $query, string $term): Builder
    {
        $term = mb_substr(trim($term), 0, 100);
        if ($term === '') {
            return $query;
        }
        if (DB::connection()->getDriverName() === 'mysql') {
            return $query->whereFullText('search_text', $term);
        }

        return $query->where('search_text', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%');
    }
}
