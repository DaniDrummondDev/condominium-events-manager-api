<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\People\DTOs\ServiceProviderVisitDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ServiceProviderVisitDTO $resource
 */
class ServiceProviderVisitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'service_provider_id' => $this->resource->serviceProviderId,
            'unit_id' => $this->resource->unitId,
            'reservation_id' => $this->resource->reservationId,
            'scheduled_date' => $this->resource->scheduledDate,
            'purpose' => $this->resource->purpose,
            'status' => $this->resource->status,
            'checked_in_at' => $this->resource->checkedInAt,
            'checked_out_at' => $this->resource->checkedOutAt,
            'checked_in_by' => $this->resource->checkedInBy,
            'notes' => $this->resource->notes,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
