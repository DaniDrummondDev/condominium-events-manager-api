<?php

declare(strict_types=1);

namespace Application\Billing\Contracts;

use Domain\Billing\Entities\NFSeDocument;
use Domain\Shared\ValueObjects\Uuid;

interface NFSeDocumentRepositoryInterface
{
    public function findById(Uuid $id): ?NFSeDocument;

    public function findByInvoiceId(Uuid $invoiceId): ?NFSeDocument;

    public function findByIdempotencyKey(string $idempotencyKey): ?NFSeDocument;

    public function findByProviderRef(string $providerRef): ?NFSeDocument;

    /**
     * @return array<NFSeDocument>
     */
    public function findByTenantId(Uuid $tenantId): array;

    public function save(NFSeDocument $document): void;
}
