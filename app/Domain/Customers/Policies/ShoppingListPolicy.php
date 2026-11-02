<?php

namespace App\Domain\Customers\Policies;

use App\Domain\Customers\Models\ShoppingList;
use App\Models\User;

final class ShoppingListPolicy
{
    public function view(User $u, ShoppingList $m): bool
    {
        return ! $u->is_blocked && $m->user_id === $u->id;
    }

    public function update(User $u, ShoppingList $m): bool
    {
        return $this->view($u, $m);
    }

    public function delete(User $u, ShoppingList $m): bool
    {
        return $this->view($u, $m);
    }
}
