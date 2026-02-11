<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use Domain\Billing\Enums\PlanStatus;
use Domain\Billing\Events\PlanCreated;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

class Plan
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private string $name,
        private readonly string $slug,
        private PlanStatus $status,
    ) {}

    public static function create(Uuid $id, string $name, string $slug): self
    {
        $plan = new self($id, $name, $slug, PlanStatus::Active);

        $plan->domainEvents[] = new PlanCreated($id);

        return $plan;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function status(): PlanStatus
    {
        return $this->status;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function activate(): void
    {
        if ($this->status === PlanStatus::Archived) {
            throw new DomainException(
                'Cannot activate an archived plan',
                'PLAN_ARCHIVED',
                ['plan_id' => $this->id->value()],
            );
        }

        $this->status = PlanStatus::Active;
    }

    public function deactivate(): void
    {
        if ($this->status === PlanStatus::Archived) {
            throw new DomainException(
                'Cannot deactivate an archived plan',
                'PLAN_ARCHIVED',
                ['plan_id' => $this->id->value()],
            );
        }

        $this->status = PlanStatus::Inactive;
    }

    public function archive(): void
    {
        $this->status = PlanStatus::Archived;
    }

    public function isAvailable(): bool
    {
        return $this->status->isAvailable();
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
}
