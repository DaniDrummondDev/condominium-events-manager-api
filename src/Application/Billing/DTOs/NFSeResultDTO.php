<?php

declare(strict_types=1);

namespace Application\Billing\DTOs;

final readonly class NFSeResultDTO
{
    /**
     * @param  array<string, mixed>  $providerResponse
     */
    public function __construct(
        public bool $success,
        public ?string $providerRef = null,
        public ?string $status = null,
        public ?string $nfseNumber = null,
        public ?string $verificationCode = null,
        public ?string $pdfUrl = null,
        public ?string $xmlContent = null,
        public ?string $errorMessage = null,
        public array $providerResponse = [],
    ) {}
}
