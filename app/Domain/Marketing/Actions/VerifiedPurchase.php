<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Orders\Models\OrderItem;
use App\Models\User;

final class VerifiedPurchase
{
    public function item(User $u, int $productId): ?OrderItem
    {
        return OrderItem::where('product_id', $productId)->whereHas('order', fn ($q) => $q->where('user_id', $u->id)->where('fulfillment_status', 'fulfilled')->whereColumn('paid_amount', '>=', 'total')->where(fn ($q) => $q->whereNull('payment_environment')->orWhere('payment_environment', 'live'))->whereNotIn('status', ['pending', 'confirmed', 'processing', 'packed', 'cancelled', 'failed', 'on-hold']))->first();
    }
}
