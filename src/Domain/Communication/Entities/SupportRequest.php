<?php

declare(strict_types=1);

namespace Domain\Communication\Entities;

use DateTimeImmutable;
use Domain\Communication\Enums\ClosedReason;
use Domain\Communication\Enums\SupportRequestCategory;
use Domain\Communication\Enums\SupportRequestPriority;
use Domain\Communication\Enums\SupportRequestStatus;
use Domain\Communication\Events\SupportRequestClosed;
use Domain\Communication\Events\SupportRequestCreated;
use Domain\Communication\Events\SupportRequestResolved;
use Domain\Communication\Events\SupportRequestUpdated;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

class SupportRequest
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $userId,
        private readonly string $subject,
        private readonly SupportRequestCategory $category,
        private SupportRequestStatus $status,
        private readonly SupportRequestPriority $priority,
        private ?DateTimeImmutable $closedAt,
        private ?ClosedReason $closedReason,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $userId,
        string $subject,
        SupportRequestCategory $category,
        SupportRequestPriority $priority,
    ): self {
        $now = new DateTimeImmutable;

        $request = new self(
            id: $id,
            userId: $userId,
            subject: $subject,
            category: $category,
            status: SupportRequestStatus::Open,
            priority: $priority,
            closedAt: null,
            closedReason: null,
            createdAt: $now,
            updatedAt: $now,
        );

        $request->domainEvents[] = new SupportRequestCreated(
            $id->value(),
            $userId->value(),
            $subject,
            $category->value,
            $priority->value,
        );

        return $request;
    }

    // ── State Transitions ───────────────────────────────────────

    public function startProgress(): void
    {
        $this->assertTransition(SupportRequestStatus::InProgress);

        $this->status = SupportRequestStatus::InProgress;
        $this->updatedAt = new DateTimeImmutable;

        $this->domainEvents[] = new SupportRequestUpdated(
            $this->id->value(),
            $this->status->value,
        );
    }

    public function resolve(): void
    {
        $this->assertTransition(SupportRequestStatus::Resolved);

        $this->status = SupportRequestStatus::Resolved;
        $this->updatedAt = new DateTimeImmutable;

        $this->domainEvents[] = new SupportRequestResolved(
            $this->id->value(),
            $this->userId->value(),
        );
    }

    public function close(ClosedReason $reason): void
    {
        $this->assertTransition(SupportRequestStatus::Closed);

        $this->status = SupportRequestStatus::Closed;
        $this->closedAt = new DateTimeImmutable;
        $this->closedReason = $reason;
        $this->updatedAt = new DateTimeImmutable;

        $this->domainEvents[] = new SupportRequestClosed(
            $this->id->value(),
            $reason->value,
        );
    }

    public function reopen(): void
    {
        $this->assertTransition(SupportRequestStatus::Open);

        $this->status = SupportRequestStatus::Open;
        $this->closedAt = null;
        $this->closedReason = null;
        $this->updatedAt = new DateTimeImmutable;

        $this->domainEvents[] = new SupportRequestUpdated(
            $this->id->value(),
            $this->status->value,
        );
    }

    // ── Getters ─────────────────────────────────────────────────

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function category(): SupportRequestCategory
    {
        return $this->category;
    }

    public function status(): SupportRequestStatus
    {
        return $this->status;
    }

    public function priority(): SupportRequestPriority
    {
        return $this->priority;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function closedReason(): ?ClosedReason
    {
        return $this->closedReason;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
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

    private function assertTransition(SupportRequestStatus $newStatus): void
    {
        if (! $this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Cannot transition support request from '{$this->status->value}' to '{$newStatus->value}'",
                'INVALID_STATUS_TRANSITION',
                [
                    'support_request_id' => $this->id->value(),
                    'current_status' => $this->status->value,
                    'target_status' => $newStatus->value,
                ],
            );
        }
    }
}
