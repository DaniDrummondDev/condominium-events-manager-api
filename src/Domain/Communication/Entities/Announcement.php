<?php

declare(strict_types=1);

namespace Domain\Communication\Entities;

use DateTimeImmutable;
use Domain\Communication\Enums\AnnouncementPriority;
use Domain\Communication\Enums\AnnouncementStatus;
use Domain\Communication\Enums\AudienceType;
use Domain\Communication\Events\AnnouncementArchived;
use Domain\Communication\Events\AnnouncementPublished;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

class Announcement
{
    /** @var array<object> */
    private array $domainEvents = [];

    /**
     * @param array<string>|null $audienceIds
     */
    public function __construct(
        private readonly Uuid $id,
        private readonly string $title,
        private readonly string $body,
        private readonly AnnouncementPriority $priority,
        private readonly AudienceType $audienceType,
        private readonly ?array $audienceIds,
        private AnnouncementStatus $status,
        private readonly Uuid $publishedBy,
        private readonly DateTimeImmutable $publishedAt,
        private readonly ?DateTimeImmutable $expiresAt,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param array<string>|null $audienceIds
     */
    public static function create(
        Uuid $id,
        string $title,
        string $body,
        AnnouncementPriority $priority,
        AudienceType $audienceType,
        ?array $audienceIds,
        Uuid $publishedBy,
        ?DateTimeImmutable $expiresAt,
    ): self {
        $now = new DateTimeImmutable;

        $announcement = new self(
            id: $id,
            title: $title,
            body: $body,
            priority: $priority,
            audienceType: $audienceType,
            audienceIds: $audienceIds,
            status: AnnouncementStatus::Published,
            publishedBy: $publishedBy,
            publishedAt: $now,
            expiresAt: $expiresAt,
            createdAt: $now,
        );

        $announcement->domainEvents[] = new AnnouncementPublished(
            $id->value(),
            $title,
            $priority->value,
            $audienceType->value,
            $publishedBy->value(),
        );

        return $announcement;
    }

    // ── State Transitions ───────────────────────────────────────

    public function archive(): void
    {
        $this->assertTransition(AnnouncementStatus::Archived);

        $this->status = AnnouncementStatus::Archived;

        $this->domainEvents[] = new AnnouncementArchived(
            $this->id->value(),
            $this->title,
        );
    }

    // ── Getters ─────────────────────────────────────────────────

    public function id(): Uuid
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function priority(): AnnouncementPriority
    {
        return $this->priority;
    }

    public function audienceType(): AudienceType
    {
        return $this->audienceType;
    }

    /**
     * @return array<string>|null
     */
    public function audienceIds(): ?array
    {
        return $this->audienceIds;
    }

    public function status(): AnnouncementStatus
    {
        return $this->status;
    }

    public function publishedBy(): Uuid
    {
        return $this->publishedBy;
    }

    public function publishedAt(): DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    // ── Private ─────────────────────────────────────────────────

    private function assertTransition(AnnouncementStatus $newStatus): void
    {
        if (! $this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Cannot transition announcement from '{$this->status->value}' to '{$newStatus->value}'",
                'INVALID_STATUS_TRANSITION',
                [
                    'announcement_id' => $this->id->value(),
                    'current_status' => $this->status->value,
                    'target_status' => $newStatus->value,
                ],
            );
        }
    }
}
