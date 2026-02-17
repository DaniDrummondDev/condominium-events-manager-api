<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class NFSeWebhookDTO
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $providerRef,
        public string $status,
        public ?string $nfseNumber = null,
        public ?string $verificationCode = null,
        public ?string $pdfUrl = null,
        public ?string $xmlContent = null,
        public ?string $errorMessage = null,
        public array $rawPayload = [],
    ) {}
}
