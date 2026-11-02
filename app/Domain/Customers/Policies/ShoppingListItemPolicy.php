<?php

namespace App\Domain\Customers\Policies;

use App\Domain\Customers\Models\ShoppingList;
use App\Domain\Customers\Models\ShoppingListItem;
use App\Models\User;

final class ShoppingListItemPolicy
{
    public function view(User $u, ShoppingListItem $m): bool
    {
        return ! $u->is_blocked && ShoppingList::whereKey($m->shopping_list_id)->where('user_id', $u->id)->exists();
    }

    public function update(User $u, ShoppingListItem $m): bool
    {
        return $this->view($u, $m);
    }

    public function delete(User $u, ShoppingListItem $m): bool
    {
        return $this->view($u, $m);
    }
}
