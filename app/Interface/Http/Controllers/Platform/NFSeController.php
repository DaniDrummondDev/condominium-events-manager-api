<?php

declare(strict_types=1);

namespace App\Interface\Http\Controllers\Platform;

use App\Infrastructure\Jobs\Billing\GenerateNFSeJob;
use App\Interface\Http\Requests\Platform\CancelNFSeRequest;
use App\Interface\Http\Resources\Platform\NFSeDocumentResource;
use Application\Billing\Contracts\NFSeDocumentRepositoryInterface;
use Application\Billing\DTOs\NFSeDocumentDTO;
use Application\Billing\UseCases\CancelNFSe;
use Domain\Billing\Entities\NFSeDocument;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NFSeController
{
    public function index(Request $request, NFSeDocumentRepositoryInterface $repository): JsonResponse
    {
        $tenantId = $request->query('tenant_id');

        if ($tenantId === null) {
            return new JsonResponse([
                'error' => 'TENANT_ID_REQUIRED',
                'message' => 'tenant_id query parameter is required',
            ], 422);
        }

        $documents = $repository->findByTenantId(Uuid::fromString($tenantId));
        $dtos = array_map(fn (NFSeDocument $doc) => $this->toDTO($doc), $documents);

        return NFSeDocumentResource::collection($dtos)->response();
    }

    public function show(string $id, NFSeDocumentRepositoryInterface $repository): JsonResponse
    {
        $document = $repository->findById(Uuid::fromString($id));

        if ($document === null) {
            return new JsonResponse([
                'error' => 'NFSE_NOT_FOUND',
                'message' => 'NFSe document not found',
            ], 404);
        }

        return (new NFSeDocumentResource($this->toDTO($document)))->response();
    }

    public function cancel(string $id, CancelNFSeRequest $request, CancelNFSe $useCase): JsonResponse
    {
        try {
            $nfse = $useCase->execute($id, $request->validated('reason'));

            return (new NFSeDocumentResource($this->toDTO($nfse)))->response();
        } catch (DomainException $e) {
            $statusCode = match ($e->errorCode()) {
                'NFSE_NOT_FOUND' => 404,
                default => 422,
            };

            return new JsonResponse([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function retry(string $id, NFSeDocumentRepositoryInterface $repository): JsonResponse
    {
        $document = $repository->findById(Uuid::fromString($id));

        if ($document === null) {
            return new JsonResponse([
                'error' => 'NFSE_NOT_FOUND',
                'message' => 'NFSe document not found',
            ], 404);
        }

        if (! $document->status()->canRetry()) {
            return new JsonResponse([
                'error' => 'NFSE_CANNOT_RETRY',
                'message' => "Cannot retry NFSe in status '{$document->status()->value}'",
            ], 422);
        }

        $document->resetForRetry();
        $repository->save($document);

        GenerateNFSeJob::dispatch($document->invoiceId()->value());

        return new JsonResponse([
            'message' => 'NFSe retry queued successfully',
            'nfse_id' => $document->id()->value(),
        ]);
    }

    public function pdf(string $id, NFSeDocumentRepositoryInterface $repository): JsonResponse
    {
        $document = $repository->findById(Uuid::fromString($id));

        if ($document === null) {
            return new JsonResponse([
                'error' => 'NFSE_NOT_FOUND',
                'message' => 'NFSe document not found',
            ], 404);
        }

        if ($document->pdfUrl() === null) {
            return new JsonResponse([
                'error' => 'NFSE_PDF_NOT_AVAILABLE',
                'message' => 'PDF not available for this NFSe',
            ], 404);
        }

        return new JsonResponse([
            'pdf_url' => $document->pdfUrl(),
            'nfse_number' => $document->nfseNumber(),
        ]);
    }

    private function toDTO(NFSeDocument $document): NFSeDocumentDTO
    {
        return new NFSeDocumentDTO(
            id: $document->id()->value(),
            tenantId: $document->tenantId()->value(),
            invoiceId: $document->invoiceId()->value(),
            status: $document->status()->value,
            providerRef: $document->providerRef(),
            nfseNumber: $document->nfseNumber(),
            verificationCode: $document->verificationCode(),
            serviceDescription: $document->serviceDescription(),
            competenceDate: $document->competenceDate()->format('Y-m-d'),
            totalAmountInCents: $document->totalAmount()->amount(),
            issRate: $document->issRate(),
            issAmountInCents: $document->issAmount()->amount(),
            pdfUrl: $document->pdfUrl(),
            errorMessage: $document->errorMessage(),
            authorizedAt: $document->authorizedAt()?->format('c'),
            cancelledAt: $document->cancelledAt()?->format('c'),
            createdAt: $document->createdAt()?->format('c'),
        );
    }
}
