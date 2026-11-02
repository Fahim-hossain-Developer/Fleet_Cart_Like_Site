<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\ShoppingList;
use App\Domain\Customers\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductLists
{
    private function guestKey(): string
    {
        $token = session('shopping_list_token');
        if (! is_string($token)) {
            $token = Str::random(48);
            session(['shopping_list_token' => $token]);
        }

        return 'guest:'.hash_hmac('sha256', $token, config('app.key'));
    }

    public function current(): ShoppingList
    {
        $u = auth('web')->user();
        abort_if($u && $u->is_blocked, 403);
        $key = $u ? 'user:'.$u->id : $this->guestKey();
        ShoppingList::upsert([['owner_key' => $key, 'user_id' => $u?->id, 'created_at' => now(), 'updated_at' => now()]], ['owner_key'], ['updated_at']);

        return ShoppingList::where('owner_key', $key)->firstOrFail();
    }

    private function kind(string $kind): void
    {
        abort_unless(in_array($kind, ['wishlist', 'compare'], true), 404);
    }

    /** @return Collection<int,Product> */
    public function products(string $kind): Collection
    {
        $this->kind($kind);
        $list = $this->current();
        $ids = ShoppingListItem::where('shopping_list_id', $list->id)->where('kind', $kind)->orderBy('id')->pluck('product_id');

        return Product::published()->whereIn('id', $ids)->with(['media', 'variants', 'categories', 'attributeValues.attribute', 'brand'])->get();
    }

    public function set(string $kind, int $productId, bool $add): void
    {
        $this->kind($kind);
        $list = $this->current();
        DB::transaction(function () use ($kind, $productId, $add, $list) {
            ShoppingList::lockForUpdate()->findOrFail($list->id);
            $q = ShoppingListItem::where('shopping_list_id', $list->id)->where('kind', $kind);
            if (! $add) {
                $q->where('product_id', $productId)->delete();

                return;
            }Product::published()->findOrFail($productId);
            ShoppingListItem::where('shopping_list_id', $list->id)->whereNotIn('product_id', Product::published()->select('id'))->delete();
            if ((clone $q)->where('product_id', $productId)->exists()) {
                return;
            }if ($q->count() >= ($kind === 'compare' ? 4 : 100)) {
                throw ValidationException::withMessages(['list' => __('marketing.list_limit')]);
            }ShoppingListItem::create(['shopping_list_id' => $list->id, 'product_id' => $productId, 'kind' => $kind]);
        });
    }

    public function mergeGuest(User $user): void
    {
        $guest = ShoppingList::where('owner_key', $this->guestKey())->first();
        if (! $guest) {
            return;
        }$target = $this->current();
        if ($target->user_id !== $user->id) {
            return;
        }
        DB::transaction(function () use ($guest, $target) {
            ShoppingList::whereIn('id', [$guest->id, $target->id])->orderBy('id')->lockForUpdate()->get();
            foreach (['wishlist' => 100, 'compare' => 4] as $kind => $limit) {
                $existing = ShoppingListItem::where('shopping_list_id', $target->id)->where('kind', $kind)->pluck('product_id')->all();
                foreach (ShoppingListItem::where('shopping_list_id', $guest->id)->where('kind', $kind)->orderBy('id')->get() as $item) {
                    if (count($existing) >= $limit) {
                        break;
                    }if (in_array($item->product_id, $existing, true) || ! Product::published()->whereKey($item->product_id)->exists()) {
                        continue;
                    }ShoppingListItem::firstOrCreate(['shopping_list_id' => $target->id, 'product_id' => $item->product_id, 'kind' => $kind]);
                    $existing[] = $item->product_id;
                }
            }ShoppingListItem::where('shopping_list_id', $guest->id)->delete();
            $guest->delete();
        });
    }
}
