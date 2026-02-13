<?php

declare(strict_types=1);

namespace Application\Communication\DTOs;

final readonly class CreateAnnouncementDTO
{
    /**
     * @param array<string>|null $audienceIds
     */
    public function __construct(
        public string $title,
        public string $body,
        public string $priority,
        public string $audienceType,
        public ?array $audienceIds,
        public string $publishedBy,
        public ?string $expiresAt,
    ) {}
}
