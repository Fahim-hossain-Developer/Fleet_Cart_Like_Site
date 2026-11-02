<?php

namespace App\Domain\Checkout\Actions;

final class PriceCalculator
{
    /** @param list<array<string,mixed>> $lines
     * @return array<string,mixed> */
    public function calculate(array $lines, int $discount, int $shipping, int $giftFee = 0, int $giftTaxBps = 0): array
    {
        $subtotal = array_sum(array_column($lines, 'subtotal'));
        $discount = max(0, min($subtotal, $discount));
        $remaining = $discount;
        $tax = 0;
        $exclusiveTax = 0;
        foreach ($lines as $index => &$line) {
            $share = $index === array_key_last($lines) ? $remaining : ($subtotal ? (int) bcdiv(bcmul((string) $discount, (string) $line['subtotal'], 0), (string) $subtotal, 0) : 0);
            $remaining -= $share;
            $net = $line['subtotal'] - $share;
            $rate = (int) ($line['tax_bps'] ?? 0);
            $lineTax = $rate ? intdiv($net * $rate + intdiv(($line['tax_inclusive'] ?? false) ? 10000 + $rate : 10000, 2), ($line['tax_inclusive'] ?? false) ? 10000 + $rate : 10000) : 0;
            $tax += $lineTax;
            if (! ($line['tax_inclusive'] ?? false)) {
                $exclusiveTax += $lineTax;
            }
            $line['discount'] = $share;
            $line['tax'] = $lineTax;
            $line['total'] = $net + (($line['tax_inclusive'] ?? false) ? 0 : $lineTax);
        }unset($line);
        $giftTax = intdiv($giftFee * $giftTaxBps + 5000, 10000);
        $tax += $giftTax;

        return ['lines' => $lines, 'subtotal' => $subtotal, 'discount' => $discount, 'shipping' => $shipping, 'gift_fee' => $giftFee, 'tax' => $tax, 'tax_included' => $tax - $exclusiveTax - $giftTax, 'total' => $subtotal - $discount + $shipping + $giftFee + $exclusiveTax + $giftTax];
    }
}
