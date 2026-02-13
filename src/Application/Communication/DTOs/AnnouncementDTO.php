<?php

declare(strict_types=1);

namespace Application\Communication\DTOs;

final readonly class AnnouncementDTO
{
    /**
     * @param array<string>|null $audienceIds
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $body,
        public string $priority,
        public string $audienceType,
        public ?array $audienceIds,
        public string $status,
        public string $publishedBy,
        public string $publishedAt,
        public ?string $expiresAt,
        public string $createdAt,
    ) {}
}
