<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Settings\Models\Currency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class CurrencyDisplay
{
    public function current(): ?Currency
    {
        return Currency::where('is_active', true)->where('code', session('display_currency', request()->cookie('display_currency', Auth::guard('web')->user()->currency ?? config('checkout.base_currency'))))->first() ?? Currency::where('code', config('checkout.base_currency'))->first();
    }

    public function rate(Currency $currency): int
    {
        return intdiv($currency->rate_ppm * (10000 + $currency->markup_bps) + 5000, 10000);
    }

    public function convert(int $amount, Currency $currency): int
    {
        $numerator = bcmul(bcmul((string) $amount, (string) $this->rate($currency), 0), (string) (10 ** $currency->decimals), 0);
        $minor = bcdiv(bcadd($numerator, '50000000', 0), '100000000', 0);
        if (bccomp($minor, (string) (PHP_INT_MAX - 10000), 0) > 0) {
            throw ValidationException::withMessages(['currency' => __('checkout.invalid_currency')]);
        }

        return intdiv((int) $minor + intdiv(max(1, $currency->rounding_increment), 2), max(1, $currency->rounding_increment)) * max(1, $currency->rounding_increment);
    }

    public function format(int $baseAmount, ?Currency $currency = null): string
    {
        $currency ??= $this->current();
        if (! $currency) {
            return (string) config('checkout.base_currency').' '.intdiv($baseAmount, 100).'.'.str_pad((string) ($baseAmount % 100), 2, '0', STR_PAD_LEFT);
        }
        $amount = $this->convert($baseAmount, $currency);
        $factor = 10 ** $currency->decimals;
        $whole = number_format(intdiv($amount, $factor), 0, '.', ',');
        $number = $whole.($currency->decimals ? '.'.str_pad((string) ($amount % $factor), $currency->decimals, '0', STR_PAD_LEFT) : '');

        return $currency->symbol_position === 'after' ? $number.' '.$currency->symbol : $currency->symbol.' '.$number;
    }
}
