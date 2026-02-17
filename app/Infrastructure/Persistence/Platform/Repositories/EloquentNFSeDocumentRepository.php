<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Platform\Repositories;

use App\Infrastructure\Persistence\Platform\Models\NFSeDocumentModel;
use Application\Billing\Contracts\NFSeDocumentRepositoryInterface;
use DateTimeImmutable;
use Domain\Billing\Entities\NFSeDocument;
use Domain\Billing\Enums\NFSeStatus;
use Domain\Shared\ValueObjects\Money;
use Domain\Shared\ValueObjects\Uuid;

class EloquentNFSeDocumentRepository implements NFSeDocumentRepositoryInterface
{
    public function findById(Uuid $id): ?NFSeDocument
    {
        $model = NFSeDocumentModel::query()->find($id->value());

        return $model ? $this->toDomain($model) : null;
    }

    public function findByInvoiceId(Uuid $invoiceId): ?NFSeDocument
    {
        $model = NFSeDocumentModel::query()
            ->where('invoice_id', $invoiceId->value())
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?NFSeDocument
    {
        $model = NFSeDocumentModel::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findByProviderRef(string $providerRef): ?NFSeDocument
    {
        $model = NFSeDocumentModel::query()
            ->where('provider_ref', $providerRef)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * @return array<NFSeDocument>
     */
    public function findByTenantId(Uuid $tenantId): array
    {
        return NFSeDocumentModel::query()
            ->where('tenant_id', $tenantId->value())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (NFSeDocumentModel $model) => $this->toDomain($model))
            ->all();
    }

    public function save(NFSeDocument $document): void
    {
        NFSeDocumentModel::query()->updateOrCreate(
            ['id' => $document->id()->value()],
            [
                'tenant_id' => $document->tenantId()->value(),
                'invoice_id' => $document->invoiceId()->value(),
                'status' => $document->status()->value,
                'provider_ref' => $document->providerRef(),
                'nfse_number' => $document->nfseNumber(),
                'verification_code' => $document->verificationCode(),
                'service_description' => $document->serviceDescription(),
                'competence_date' => $document->competenceDate()->format('Y-m-d'),
                'total_amount' => $document->totalAmount()->amount() / 100,
                'iss_rate' => $document->issRate(),
                'iss_amount' => $document->issAmount()->amount() / 100,
                'pdf_url' => $document->pdfUrl(),
                'xml_content' => $document->xmlContent(),
                'provider_response' => $document->providerResponse(),
                'authorized_at' => $document->authorizedAt(),
                'cancelled_at' => $document->cancelledAt(),
                'error_message' => $document->errorMessage(),
                'idempotency_key' => $document->idempotencyKey(),
            ],
        );
    }

    private function toDomain(NFSeDocumentModel $model): NFSeDocument
    {
        return new NFSeDocument(
            id: Uuid::fromString($model->id),
            tenantId: Uuid::fromString($model->tenant_id),
            invoiceId: Uuid::fromString($model->invoice_id),
            status: NFSeStatus::from($model->status),
            providerRef: $model->provider_ref,
            nfseNumber: $model->nfse_number,
            verificationCode: $model->verification_code,
            serviceDescription: $model->service_description,
            competenceDate: new DateTimeImmutable($model->competence_date->format('Y-m-d')),
            totalAmount: new Money((int) round((float) $model->total_amount * 100)),
            issRate: (float) $model->iss_rate,
            issAmount: new Money((int) round((float) $model->iss_amount * 100)),
            pdfUrl: $model->pdf_url,
            xmlContent: $model->xml_content,
            providerResponse: $model->provider_response,
            authorizedAt: $model->authorized_at
                ? new DateTimeImmutable($model->authorized_at->format('c'))
                : null,
            cancelledAt: $model->cancelled_at
                ? new DateTimeImmutable($model->cancelled_at->format('c'))
                : null,
            errorMessage: $model->error_message,
            idempotencyKey: $model->idempotency_key,
            createdAt: $model->created_at
                ? new DateTimeImmutable($model->created_at->format('c'))
                : null,
        );
    }
}
