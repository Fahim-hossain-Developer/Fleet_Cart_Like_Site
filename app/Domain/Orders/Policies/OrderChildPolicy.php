<?php

namespace App\Domain\Orders\Policies;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderEvent;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderChildPolicy
{
    public function view(Admin|User $user, Model $record): bool
    {
        if ($user instanceof Admin) {
            return $user->is_active && $user->hasPermissionTo('orders.manage', 'admin');
        }
        if ($user->is_blocked) {
            return false;
        }
        if ($record instanceof OrderEvent && ! $record->is_public) {
            return false;
        }
        $id = $record->getAttribute('order_id');
        if (! $id && $record->getAttribute('order_item_id')) {
            $id = DB::table('order_items')->where('id', $record->getAttribute('order_item_id'))->value('order_id');
        }

        return $id && Order::whereKey($id)->where('user_id', $user->id)->exists();
    }
}
