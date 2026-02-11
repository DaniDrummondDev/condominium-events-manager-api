<?php

declare(strict_types=1);

namespace Domain\Governance\Entities;

use DateTimeImmutable;
use Domain\Governance\Enums\ContestationStatus;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

class ViolationContestation
{
    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $violationId,
        private readonly Uuid $tenantUserId,
        private readonly string $reason,
        private ContestationStatus $status,
        private ?string $response,
        private ?Uuid $respondedBy,
        private ?DateTimeImmutable $respondedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $violationId,
        Uuid $tenantUserId,
        string $reason,
    ): self {
        return new self(
            id: $id,
            violationId: $violationId,
            tenantUserId: $tenantUserId,
            reason: $reason,
            status: ContestationStatus::Pending,
            response: null,
            respondedBy: null,
            respondedAt: null,
            createdAt: new DateTimeImmutable,
        );
    }

    public function accept(Uuid $respondedBy, string $response): void
    {
        $this->ensurePending();

        $this->status = ContestationStatus::Accepted;
        $this->respondedBy = $respondedBy;
        $this->response = $response;
        $this->respondedAt = new DateTimeImmutable;
    }

    public function reject(Uuid $respondedBy, string $response): void
    {
        $this->ensurePending();

        $this->status = ContestationStatus::Rejected;
        $this->respondedBy = $respondedBy;
        $this->response = $response;
        $this->respondedAt = new DateTimeImmutable;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function violationId(): Uuid
    {
        return $this->violationId;
    }

    public function tenantUserId(): Uuid
    {
        return $this->tenantUserId;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function status(): ContestationStatus
    {
        return $this->status;
    }

    public function response(): ?string
    {
        return $this->response;
    }

    public function respondedBy(): ?Uuid
    {
        return $this->respondedBy;
    }

    public function respondedAt(): ?DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function ensurePending(): void
    {
        if ($this->status !== ContestationStatus::Pending) {
            throw DomainException::businessRule(
                'CONTESTATION_ALREADY_REVIEWED',
                "Contestation has already been reviewed with status: {$this->status->value}",
                [
                    'contestation_id' => $this->id->value(),
                    'status' => $this->status->value,
                ],
            );
        }
    }
}
