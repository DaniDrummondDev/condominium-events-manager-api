<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Reservation\DTOs\ReservationDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ReservationDTO $resource
 */
class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'space_id' => $this->resource->spaceId,
            'unit_id' => $this->resource->unitId,
            'resident_id' => $this->resource->residentId,
            'status' => $this->resource->status,
            'title' => $this->resource->title,
            'start_datetime' => $this->resource->startDatetime,
            'end_datetime' => $this->resource->endDatetime,
            'expected_guests' => $this->resource->expectedGuests,
            'notes' => $this->resource->notes,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
