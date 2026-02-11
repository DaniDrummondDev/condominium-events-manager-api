<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use DateTimeImmutable;
use Domain\Billing\Entities\Invoice;
use Domain\Shared\ValueObjects\Uuid;

interface InvoiceRepositoryInterface
{
    public function findById(Uuid $id): ?Invoice;

    /**
     * @return array<Invoice>
     */
    public function findByTenantId(Uuid $tenantId): array;

    /**
     * @return array<Invoice>
     */
    public function findBySubscriptionId(Uuid $subscriptionId): array;

    /**
     * @return array<Invoice>
     */
    public function findPastDue(): array;

    public function findBySubscriptionAndPeriod(
        Uuid $subscriptionId,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
    ): ?Invoice;

    public function save(Invoice $invoice): void;
}
