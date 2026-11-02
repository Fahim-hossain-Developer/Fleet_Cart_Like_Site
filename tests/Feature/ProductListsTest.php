<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Actions\ProductLists;
use App\Domain\Customers\Models\ShoppingListItem;
use App\Models\User;

it('persists a guest wishlist without revealing another browser list', function () {
    $p = Product::factory()->create();
    $this->post('/product-lists/wishlist', ['product_id' => $p->id, 'add' => 1])->assertRedirect();
    $this->get('/wishlist')->assertOk()->assertSee($p->name);
    $this->flushSession();
    $this->get('/wishlist')->assertOk()->assertDontSee($p->name);
});
it('bounds comparison to four products and replays additions without duplicates', function () {
    $products = Product::factory()->count(5)->create();
    foreach ($products->take(4) as $p) {
        $this->post('/product-lists/compare', ['product_id' => $p->id, 'add' => 1])->assertSessionHasNoErrors();
    }
    $this->post('/product-lists/compare', ['product_id' => $products[0]->id, 'add' => 1])->assertSessionHasNoErrors();
    $this->post('/product-lists/compare', ['product_id' => $products[4]->id, 'add' => 1])->assertSessionHasErrors('list');
    expect(ShoppingListItem::where('kind', 'compare')->count())->toBe(4);
    $this->get('/compare')->assertOk()->assertSee($products[0]->name)->assertSee($products[3]->name)->assertDontSee($products[4]->name);
});
it('merges a browser list only into the signed-in owner and keeps account lists isolated', function () {
    $p = Product::factory()->create();
    $this->post('/product-lists/wishlist', ['product_id' => $p->id, 'add' => 1]);
    $u = User::factory()->create();
    $this->actingAs($u, 'web');
    app(ProductLists::class)->mergeGuest($u);
    $this->get('/wishlist')->assertOk()->assertSee($p->name);
    $this->flushSession();
    $other = User::factory()->create();
    $this->actingAs($other, 'web');
    $this->get('/wishlist')->assertOk()->assertDontSee($p->name);
    $this->post('/product-lists/wishlist', ['product_id' => $p->id, 'add' => 0, 'shopping_list_id' => 1]);
    expect(ShoppingListItem::where('product_id', $p->id)->count())->toBe(1);
});
it('does not expose draft products in saved lists', function () {
    $p = Product::factory()->create();
    $this->post('/product-lists/wishlist', ['product_id' => $p->id, 'add' => 1]);
    $p->update(['status' => 'draft']);
    $this->get('/wishlist')->assertOk()->assertDontSee($p->name);
    $this->post('/product-lists/compare', ['product_id' => $p->id, 'add' => 1])->assertNotFound();
});

it('makes comparison capacity available when a saved product is unpublished', function () {
    $products = Product::factory()->count(5)->create();
    foreach ($products->take(4) as $product) {
        app(ProductLists::class)->set('compare', $product->id, true);
    }
    $products[0]->update(['status' => 'draft']);
    app(ProductLists::class)->set('compare', $products[4]->id, true);
    expect(ShoppingListItem::where('kind', 'compare')->count())->toBe(4);
});
