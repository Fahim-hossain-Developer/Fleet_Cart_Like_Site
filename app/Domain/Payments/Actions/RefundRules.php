<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderReturn;
use Illuminate\Validation\ValidationException;

final class RefundRules
{
    /**
     * @param array<int,int> $restock */
    public function validate(Order $o, int $amount, array $restock, ?int $except = null): ?OrderReturn
    {
        app(PendingOperations::class)->refundFree($o, $except);
        if (app()->environment('production') && $o->payment_environment === 'sandbox') {
            $this->fail();
        }
        $shipped = $o->shipments()->exists();
        $r = $o->returns()->where('status', 'approved')->first();
        if ($amount < 1 || ($shipped && ! $r) || (! $shipped && ! in_array($o->status, ['cancelled', 'failed'])) || $amount > $o->paid_amount - $o->refunded_amount) {
            $this->fail();
        }
        if ($shipped) {
            $cap = 0;
            foreach ($r->items as $id => $qty) {
                $item = $o->items()->findOrFail($id);
                $cap += intdiv($item->total * $qty, $item->qty);
            }if ($o->refunded_amount + $amount > $cap) {
                $this->fail();
            }
        }
        foreach ($restock as $id => $qty) {
            $i = $o->items()->find($id);
            if (! $i || $qty < 0 || $qty > $i->stock_deducted - $i->restocked_qty) {
                $this->fail();
            }if ($shipped) {
                $already = 0;
                foreach ($o->refunds as $previous) {
                    $already += (int) ($previous->items[$id] ?? 0);
                }if ($qty + $already > ($r->items[$id] ?? 0)) {
                    $this->fail();
                }
            }
        }

        return $r;
    }

    private function fail(): never
    {
        throw ValidationException::withMessages(['order' => __('orders.invalid_operation')]);
    }
}
