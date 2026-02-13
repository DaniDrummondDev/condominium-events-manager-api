<?php

declare(strict_types=1);

namespace Domain\Communication\Entities;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;

class SupportMessage
{
    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $supportRequestId,
        private readonly Uuid $senderId,
        private readonly string $body,
        private readonly bool $isInternal,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $supportRequestId,
        Uuid $senderId,
        string $body,
        bool $isInternal,
    ): self {
        return new self(
            id: $id,
            supportRequestId: $supportRequestId,
            senderId: $senderId,
            body: $body,
            isInternal: $isInternal,
            createdAt: new DateTimeImmutable,
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function supportRequestId(): Uuid
    {
        return $this->supportRequestId;
    }

    public function senderId(): Uuid
    {
        return $this->senderId;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
