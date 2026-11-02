<?php

namespace App\Domain\Payments\Actions;

final class ProviderMoney
{
    public static function minor(mixed $value): int
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new IntegrationFailure('protocol', 'invalid_amount');
        }
        $text = (string) $value;
        if (! preg_match('/^([0-9]{1,10})(?:\.([0-9]{1,2}))?$/D', $text, $m)) {
            throw new IntegrationFailure('protocol', 'invalid_amount');
        }
        $minor = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
        if ($minor > 100000000000) {
            throw new IntegrationFailure('protocol', 'invalid_amount');
        }

        return $minor;
    }

    public static function decimal(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
