<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\NFSeDocumentRepositoryInterface;
use Application\Billing\Contracts\NFSeProviderInterface;
use Application\Billing\DTOs\NFSeWebhookDTO;
use Domain\Shared\Exceptions\DomainException;

final readonly class HandleNFSeWebhook
{
    public function __construct(
        private NFSeDocumentRepositoryInterface $nfseRepository,
        private NFSeProviderInterface $nfseProvider,
    ) {}

    public function execute(string $rawPayload, string $signature, NFSeWebhookDTO $dto): void
    {
        if (! $this->nfseProvider->verifyWebhookSignature($rawPayload, $signature)) {
            throw new DomainException(
                'Invalid webhook signature',
                'INVALID_WEBHOOK_SIGNATURE',
            );
        }

        $nfse = $this->nfseRepository->findByProviderRef($dto->providerRef);

        if ($nfse === null) {
            throw new DomainException(
                'NFSe document not found for provider reference',
                'NFSE_NOT_FOUND',
                ['provider_ref' => $dto->providerRef],
            );
        }

        match ($dto->status) {
            'authorized', 'autorizada' => $nfse->markAuthorized(
                $dto->nfseNumber ?? '',
                $dto->verificationCode ?? '',
                $dto->pdfUrl,
                $dto->xmlContent,
                $dto->rawPayload,
            ),
            'denied', 'erro', 'rejeitada' => $nfse->markDenied(
                $dto->errorMessage ?? 'Denied by fiscal authority',
                $dto->rawPayload,
            ),
            'cancelled', 'cancelada' => $nfse->cancel(
                $dto->errorMessage ?? 'Cancelled via webhook',
            ),
            default => null,
        };

        $this->nfseRepository->save($nfse);
    }
}
