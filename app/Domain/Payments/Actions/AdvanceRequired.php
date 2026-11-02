<?php

namespace App\Domain\Payments\Actions;

final class AdvanceRequired
{
    public function calculate(int $total, int $shipping, string $method): int
    {
        if ($method !== 'cod') {
            return 0;
        }$value = (int) store_setting('cod_advance_value', 0);

        return min($total, match (store_setting('cod_advance_mode', 'off')) {
            'fixed' => $value,'percent' => intdiv($total * min(10000, $value) + 5000, 10000),'delivery' => $shipping,default => 0
        });
    }
}
