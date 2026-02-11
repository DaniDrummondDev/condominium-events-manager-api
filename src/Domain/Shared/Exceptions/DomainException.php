<?php

declare(strict_types=1);

namespace Domain\Shared\Exceptions;

use RuntimeException;

class DomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function businessRule(
        string $errorCode,
        string $message,
        array $context = [],
    ): self {
        return new self(
            message: $message,
            errorCode: $errorCode,
            context: $context,
        );
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
