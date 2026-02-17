<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class NFSeDocumentDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $invoiceId,
        public string $status,
        public ?string $providerRef,
        public ?string $nfseNumber,
        public ?string $verificationCode,
        public string $serviceDescription,
        public string $competenceDate,
        public int $totalAmountInCents,
        public float $issRate,
        public int $issAmountInCents,
        public ?string $pdfUrl,
        public ?string $errorMessage,
        public ?string $authorizedAt,
        public ?string $cancelledAt,
        public ?string $createdAt,
    ) {}
}
