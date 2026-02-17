<?php

declare(strict_types=1);

namespace Domain\Billing\ValueObjects;

use Domain\Shared\Exceptions\DomainException;

final readonly class Cnpj implements \Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        $digits = preg_replace('/\D/', '', $value);

        if ($digits === null || strlen($digits) !== 14) {
            throw new DomainException(
                'CNPJ must have exactly 14 digits',
                'INVALID_CNPJ',
                ['value' => $value],
            );
        }

        if (preg_match('/^(\d)\1{13}$/', $digits)) {
            throw new DomainException(
                'CNPJ with all identical digits is invalid',
                'INVALID_CNPJ',
                ['value' => $value],
            );
        }

        if (! self::validateCheckDigits($digits)) {
            throw new DomainException(
                'CNPJ check digits are invalid',
                'INVALID_CNPJ',
                ['value' => $value],
            );
        }

        $this->value = $digits;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function formatted(): string
    {
        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($this->value, 0, 2),
            substr($this->value, 2, 3),
            substr($this->value, 5, 3),
            substr($this->value, 8, 4),
            substr($this->value, 12, 2),
        );
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function validateCheckDigits(string $digits): bool
    {
        // First check digit
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits[$i] * $weights1[$i];
        }

        $remainder = $sum % 11;
        $expected1 = $remainder < 2 ? 0 : 11 - $remainder;

        if ((int) $digits[12] !== $expected1) {
            return false;
        }

        // Second check digit
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $digits[$i] * $weights2[$i];
        }

        $remainder = $sum % 11;
        $expected2 = $remainder < 2 ? 0 : 11 - $remainder;

        return (int) $digits[13] === $expected2;
    }
}
