<?php

declare(strict_types=1);

namespace Domain\Tenant\Entities;

use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Domain\Tenant\Enums\CondominiumType;
use Domain\Tenant\Enums\TenantStatus;

class Tenant
{
    /** @var array<object> */
    private array $domainEvents = [];

    public function __construct(
        private readonly Uuid $id,
        private readonly string $slug,
        private string $name,
        private readonly CondominiumType $type,
        private TenantStatus $status,
        private ?string $databaseName = null,
    ) {}

    public static function create(
        Uuid $id,
        string $slug,
        string $name,
        CondominiumType $type,
    ): self {
        $tenant = new self($id, $slug, $name, $type, TenantStatus::Prospect);

        return $tenant;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): CondominiumType
    {
        return $this->type;
    }

    public function status(): TenantStatus
    {
        return $this->status;
    }

    public function databaseName(): ?string
    {
        return $this->databaseName;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function startProvisioning(): void
    {
        $this->transitionTo(TenantStatus::Provisioning);
        $this->databaseName = 'tenant_'.$this->slug;
    }

    public function activate(): void
    {
        $this->transitionTo(TenantStatus::Active);
    }

    public function markPastDue(): void
    {
        $this->transitionTo(TenantStatus::PastDue);
    }

    public function suspend(): void
    {
        $this->transitionTo(TenantStatus::Suspended);
    }

    public function cancel(): void
    {
        $this->transitionTo(TenantStatus::Canceled);
    }

    public function archive(): void
    {
        $this->transitionTo(TenantStatus::Archived);
    }

    public function reactivate(): void
    {
        $this->transitionTo(TenantStatus::Active);
    }

    public function startTrial(): void
    {
        $this->transitionTo(TenantStatus::Trial);
    }

    public function rollbackProvisioning(): void
    {
        $this->transitionTo(TenantStatus::Prospect);
        $this->databaseName = null;
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

    private function transitionTo(TenantStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new DomainException(
                "Cannot transition tenant from '{$this->status->value}' to '{$target->value}'",
                'INVALID_TENANT_TRANSITION',
                [
                    'tenant_id' => $this->id->value(),
                    'current_status' => $this->status->value,
                    'target_status' => $target->value,
                    'allowed' => array_map(
                        fn (TenantStatus $s) => $s->value,
                        $this->status->allowedTransitions(),
                    ),
                ],
            );
        }

        $this->status = $target;
    }
}
