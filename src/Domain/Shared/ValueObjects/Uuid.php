<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObjects;

use Domain\Shared\Exceptions\DomainException;
use Symfony\Component\Uid\Uuid as SymfonyUuid;
use Symfony\Component\Uid\UuidV7;

final readonly class Uuid implements \Stringable
{
    private string $value;

    private function __construct(string $value)
    {
        if (! SymfonyUuid::isValid($value)) {
            throw new DomainException(
                "Invalid UUID: {$value}",
                'INVALID_UUID',
                ['value' => $value],
            );
        }

        $this->value = strtolower($value);
    }

    public static function generate(): self
    {
        return new self((string) new UuidV7);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
