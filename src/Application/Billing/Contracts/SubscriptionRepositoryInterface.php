<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use DateTimeImmutable;
use Domain\Billing\Entities\Subscription;
use Domain\Shared\ValueObjects\Uuid;

interface SubscriptionRepositoryInterface
{
    public function findById(Uuid $id): ?Subscription;

    public function findActiveByTenantId(Uuid $tenantId): ?Subscription;

    /**
     * @return array<Subscription>
     */
    public function findByTenantId(Uuid $tenantId): array;

    /**
     * @return array<Subscription>
     */
    public function findDueForRenewal(DateTimeImmutable $now): array;

    public function save(Subscription $subscription): void;
}
