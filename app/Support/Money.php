<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

final readonly class Money
{
    public function __construct(public int $amount, public string $currency = 'BDT')
    {
        if ($amount < 0 || ! preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new InvalidArgumentException('Money requires nonnegative minor units and an ISO currency code.');
        }
    }

    public function add(self $other): self
    {
        $this->assertCurrency($other);
        if ($other->amount > PHP_INT_MAX - $this->amount) {
            throw new OverflowException('Money overflow.');
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }
        if ($quantity > 0 && $this->amount > intdiv(PHP_INT_MAX, $quantity)) {
            throw new OverflowException('Money overflow.');
        }

        return new self($this->amount * $quantity, $this->currency);
    }

    public function decimal(): string
    {
        return intdiv($this->amount, 100).'.'.str_pad((string) ($this->amount % 100), 2, '0', STR_PAD_LEFT);
    }

    private function assertCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}
