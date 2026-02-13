<?php

declare(strict_types=1);

namespace Domain\Communication\Entities;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;

class AnnouncementRead
{
    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $announcementId,
        private readonly Uuid $userId,
        private readonly DateTimeImmutable $readAt,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $announcementId,
        Uuid $userId,
    ): self {
        return new self(
            id: $id,
            announcementId: $announcementId,
            userId: $userId,
            readAt: new DateTimeImmutable,
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function announcementId(): Uuid
    {
        return $this->announcementId;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function readAt(): DateTimeImmutable
    {
        return $this->readAt;
    }
}
