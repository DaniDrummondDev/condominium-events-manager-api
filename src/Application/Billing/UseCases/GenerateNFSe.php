<?php

declare(strict_types=1);

namespace Application\Billing\UseCases;

use Application\Billing\Contracts\InvoiceRepositoryInterface;
use Application\Billing\Contracts\NFSeDocumentRepositoryInterface;
use Application\Billing\Contracts\NFSeProviderInterface;
use Application\Billing\DTOs\NFSeRequestDTO;
use Domain\Billing\Entities\NFSeDocument;
use Domain\Billing\ValueObjects\Cnpj;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

final readonly class GenerateNFSe
{
    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepository,
        private NFSeDocumentRepositoryInterface $nfseRepository,
        private NFSeProviderInterface $nfseProvider,
    ) {}

    public function execute(string $invoiceId): NFSeDocument
    {
        $invoice = $this->invoiceRepository->findById(Uuid::fromString($invoiceId));

        if ($invoice === null) {
            throw new DomainException(
                'Invoice not found',
                'INVOICE_NOT_FOUND',
                ['invoice_id' => $invoiceId],
            );
        }

        // Idempotency: check if NFSe already exists for this invoice
        $idempotencyKey = "nfse:{$invoiceId}";
        $existing = $this->nfseRepository->findByIdempotencyKey($idempotencyKey);

        if ($existing !== null && $existing->status()->value !== 'denied') {
            return $existing;
        }

        $issRate = (float) config('fiscal.emitter.iss_rate', 5.00);
        $totalAmount = $invoice->total();
        $issAmountValue = (int) round($totalAmount->amount() * ($issRate / 100));
        $issAmount = new Money($issAmountValue, $invoice->currency());

        $competenceDate = $invoice->paidAt() ?? $invoice->dueDate();

        $emitterCnpj = config('fiscal.emitter.cnpj');
        if (empty($emitterCnpj)) {
            throw new DomainException(
                'Emitter CNPJ is not configured',
                'EMITTER_CNPJ_NOT_CONFIGURED',
            );
        }

        // Validate emitter CNPJ
        Cnpj::fromString($emitterCnpj);

        $nfseId = Uuid::generate();

        $nfse = NFSeDocument::create(
            $nfseId,
            $invoice->tenantId(),
            $invoice->id(),
            $this->buildServiceDescription($invoice),
            $competenceDate,
            $totalAmount,
            $issRate,
            $issAmount,
            $idempotencyKey,
        );

        $requestDTO = new NFSeRequestDTO(
            referenceId: $nfseId->value(),
            serviceDescription: $nfse->serviceDescription(),
            competenceDate: $competenceDate->format('Y-m-d'),
            totalAmountInCents: $totalAmount->amount(),
            issRate: $issRate,
            issAmountInCents: $issAmountValue,
            emitter: [
                'cnpj' => config('fiscal.emitter.cnpj'),
                'razao_social' => config('fiscal.emitter.razao_social'),
                'inscricao_municipal' => config('fiscal.emitter.inscricao_municipal'),
                'codigo_municipio' => config('fiscal.emitter.codigo_municipio'),
                'uf' => config('fiscal.emitter.uf'),
                'cnae' => config('fiscal.emitter.cnae'),
                'codigo_servico' => config('fiscal.emitter.codigo_servico'),
                'regime_tributario' => config('fiscal.emitter.regime_tributario'),
            ],
            recipient: [
                'tenant_id' => $invoice->tenantId()->value(),
            ],
            metadata: [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoice->invoiceNumber()->value(),
            ],
        );

        $result = $this->nfseProvider->emit($requestDTO);

        if ($result->success && $result->providerRef !== null) {
            $nfse->markProcessing($result->providerRef);

            // If provider returned authorized immediately (sync response)
            if ($result->status === 'authorized' && $result->nfseNumber !== null) {
                $nfse->markAuthorized(
                    $result->nfseNumber,
                    $result->verificationCode ?? '',
                    $result->pdfUrl,
                    $result->xmlContent,
                    $result->providerResponse,
                );
            }
        } else {
            $nfse->markProcessing($result->providerRef ?? 'pending');

            if (! $result->success) {
                $nfse->markDenied(
                    $result->errorMessage ?? 'Unknown error from provider',
                    $result->providerResponse,
                );
            }
        }

        $this->nfseRepository->save($nfse);

        return $nfse;
    }

    private function buildServiceDescription(\Domain\Billing\Entities\Invoice $invoice): string
    {
        return sprintf(
            'Assinatura de plataforma de gestão condominial - Fatura %s',
            $invoice->invoiceNumber()->value(),
        );
    }
}
