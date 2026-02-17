<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\NFSeDocumentRepositoryInterface;
use Application\Billing\Contracts\NFSeProviderInterface;
use Domain\Billing\Entities\NFSeDocument;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;

final readonly class CancelNFSe
{
    public function __construct(
        private NFSeDocumentRepositoryInterface $nfseRepository,
        private NFSeProviderInterface $nfseProvider,
    ) {}

    public function execute(string $nfseId, string $reason): NFSeDocument
    {
        $nfse = $this->nfseRepository->findById(Uuid::fromString($nfseId));

        if ($nfse === null) {
            throw new DomainException(
                'NFSe document not found',
                'NFSE_NOT_FOUND',
                ['nfse_id' => $nfseId],
            );
        }

        if ($nfse->providerRef() !== null) {
            $result = $this->nfseProvider->cancel($nfse->providerRef(), $reason);

            if (! $result->success) {
                throw new DomainException(
                    $result->errorMessage ?? 'Failed to cancel NFSe at provider',
                    'NFSE_CANCEL_FAILED',
                    ['nfse_id' => $nfseId, 'provider_ref' => $nfse->providerRef()],
                );
            }
        }

        $nfse->cancel($reason);
        $this->nfseRepository->save($nfse);

        return $nfse;
    }
}
