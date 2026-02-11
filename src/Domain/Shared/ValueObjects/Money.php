<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObjects;

use Domain\Shared\Exceptions\DomainException;

final readonly class Money implements \Stringable
{
    /**
     * @param  int  $amount  Valor em centavos (ex: 1500 = R$ 15,00)
     * @param  string  $currency  Codigo ISO 4217 (ex: BRL, USD)
     */
    public function __construct(
        private int $amount,
        private string $currency = 'BRL',
    ) {
        if ($amount < 0) {
            throw new DomainException(
                'Money amount cannot be negative',
                'INVALID_MONEY_AMOUNT',
                ['amount' => $amount],
            );
        }

        if (strlen($currency) !== 3) {
            throw new DomainException(
                'Currency must be a 3-letter ISO 4217 code',
                'INVALID_CURRENCY',
                ['currency' => $currency],
            );
        }
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->ensureSameCurrency($other);

        if ($this->amount < $other->amount) {
            throw new DomainException(
                'Cannot subtract: result would be negative',
                'MONEY_NEGATIVE_RESULT',
                ['this' => $this->amount, 'other' => $other->amount],
            );
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->amount * $factor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function greaterThan(self $other): bool
    {
        $this->ensureSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function __toString(): string
    {
        return sprintf('%s %s', number_format($this->amount / 100, 2, ',', '.'), $this->currency);
    }

    private function ensureSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new DomainException(
                'Cannot operate on different currencies',
                'CURRENCY_MISMATCH',
                ['this' => $this->currency, 'other' => $other->currency],
            );
        }
    }
}
