<?php

declare(strict_types=1);

namespace Domain\Billing\ValueObjects;

use Domain\Shared\Exceptions\DomainException;

final readonly class InvoiceNumber implements \Stringable
{
    private const string PATTERN = '/^INV-\d{4}-\d{4,}$/';

    public function __construct(
        private int $year,
        private int $sequence,
    ) {
        if ($year < 2020 || $year > 2099) {
            throw new DomainException(
                'Invoice year must be between 2020 and 2099',
                'INVALID_INVOICE_YEAR',
                ['year' => $year],
            );
        }

        if ($sequence < 1) {
            throw new DomainException(
                'Invoice sequence must be positive',
                'INVALID_INVOICE_SEQUENCE',
                ['sequence' => $sequence],
            );
        }
    }

    public static function generate(int $year, int $sequence): self
    {
        return new self($year, $sequence);
    }

    public static function fromString(string $value): self
    {
        if (! preg_match(self::PATTERN, $value)) {
            throw new DomainException(
                "Invalid invoice number format: {$value}",
                'INVALID_INVOICE_NUMBER_FORMAT',
                ['value' => $value],
            );
        }

        $parts = explode('-', $value);
        $year = (int) $parts[1];
        $sequence = (int) $parts[2];

        return new self($year, $sequence);
    }

    public function year(): int
    {
        return $this->year;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function value(): string
    {
        return sprintf('INV-%04d-%04d', $this->year, $this->sequence);
    }

    public function __toString(): string
    {
        return $this->value();
    }
}
