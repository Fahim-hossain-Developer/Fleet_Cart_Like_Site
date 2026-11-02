<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\ShoppingList;
use App\Domain\Customers\Models\ShoppingListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShoppingListItem> */
class ShoppingListItemFactory extends Factory
{
    protected $model = ShoppingListItem::class;

    public function definition(): array
    {
        return ['shopping_list_id' => ShoppingList::factory(), 'product_id' => Product::factory(), 'kind' => 'wishlist'];
    }
}
