<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Marketing\Models\CartRule;

final class AutomaticDiscounts
{
    /** @return array{discount:int,free_shipping:bool,rules:array<int,array<string,mixed>>} */
    public function calculate(int $subtotal, int $quantity, bool $lock = false): array
    {
        $discount = 0;
        $free = false;
        $applied = [];
        foreach (CartRule::where('is_active', true)->where('minimum', '<=', $subtotal)->where('minimum_qty', '<=', $quantity)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->orderBy('priority')->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())->get() as $rule) {
            $remaining = $subtotal - $discount;
            $amount = match ($rule->type) {
                'percent' => intdiv($remaining * min(100, $rule->value) + 50, 100),'fixed' => min($remaining, $rule->value),default => 0
            };
            if ($rule->max_discount !== null) {
                $amount = min($amount, $rule->max_discount);
            }$amount = min($amount, $remaining);
            $discount += $amount;
            $free = $free || $rule->type === 'free_shipping';
            if ($amount || $rule->type === 'free_shipping') {
                $applied[] = ['id' => $rule->id, 'name' => $rule->getTranslations('name'), 'discount' => $amount, 'free_shipping' => $rule->type === 'free_shipping'];
                if (! $rule->stackable) {
                    break;
                }
            }
        }

        return ['discount' => $discount, 'free_shipping' => $free, 'rules' => $applied];
    }
}
