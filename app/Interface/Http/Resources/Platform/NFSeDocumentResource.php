<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Platform;

use Application\Billing\DTOs\NFSeDocumentDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property NFSeDocumentDTO $resource
 */
class NFSeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'tenant_id' => $this->resource->tenantId,
            'invoice_id' => $this->resource->invoiceId,
            'status' => $this->resource->status,
            'provider_ref' => $this->resource->providerRef,
            'nfse_number' => $this->resource->nfseNumber,
            'verification_code' => $this->resource->verificationCode,
            'service_description' => $this->resource->serviceDescription,
            'competence_date' => $this->resource->competenceDate,
            'total_amount_in_cents' => $this->resource->totalAmountInCents,
            'iss_rate' => $this->resource->issRate,
            'iss_amount_in_cents' => $this->resource->issAmountInCents,
            'pdf_url' => $this->resource->pdfUrl,
            'error_message' => $this->resource->errorMessage,
            'authorized_at' => $this->resource->authorizedAt,
            'cancelled_at' => $this->resource->cancelledAt,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
