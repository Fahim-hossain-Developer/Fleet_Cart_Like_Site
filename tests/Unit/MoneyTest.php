<?php

use App\Support\Money;

it('uses exact minor units for arithmetic and decimal output', function () {
    $price = new Money(125050);
    expect($price->add(new Money(50))->amount)->toBe(125100)
        ->and($price->subtract(new Money(50))->decimal())->toBe('1250.00')
        ->and($price->multiply(3)->amount)->toBe(375150)
        ->and((new Money(1))->decimal())->toBe('0.01');
});
it('rejects currency mixing', fn () => (new Money(10))->add(new Money(10, 'USD')))->throws(InvalidArgumentException::class);
it('rejects negative amounts', fn () => new Money(-1))->throws(InvalidArgumentException::class);
it('rejects negative quantities', fn () => (new Money(10))->multiply(-1))->throws(InvalidArgumentException::class);
it('rejects negative subtraction', fn () => (new Money(1))->subtract(new Money(2)))->throws(InvalidArgumentException::class);
it('rejects arithmetic overflow', fn () => (new Money(PHP_INT_MAX))->add(new Money(1)))->throws(OverflowException::class);
it('rejects multiplication overflow', fn () => (new Money(PHP_INT_MAX))->multiply(2))->throws(OverflowException::class);
it('rejects malformed currency codes', fn () => new Money(1, 'bdt'))->throws(InvalidArgumentException::class);
