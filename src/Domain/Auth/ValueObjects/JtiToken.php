<?php

declare(strict_types=1);

namespace Domain\Auth\ValueObjects;

use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class JtiToken implements \Stringable
{
    private function __construct(
        private string $value,
    ) {
        if (trim($value) === '') {
            throw new DomainException(
                'JTI token cannot be empty',
                'INVALID_JTI',
            );
        }
    }

    public static function generate(TokenType $type): self
    {
        return new self($type->prefix().Uuid::generate()->value());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
