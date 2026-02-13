<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\People\DTOs\GuestDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property GuestDTO $resource
 */
class GuestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'reservation_id' => $this->resource->reservationId,
            'name' => $this->resource->name,
            'document' => $this->resource->document,
            'phone' => $this->resource->phone,
            'vehicle_plate' => $this->resource->vehiclePlate,
            'relationship' => $this->resource->relationship,
            'status' => $this->resource->status,
            'checked_in_at' => $this->resource->checkedInAt,
            'checked_out_at' => $this->resource->checkedOutAt,
            'checked_in_by' => $this->resource->checkedInBy,
            'denied_by' => $this->resource->deniedBy,
            'denied_reason' => $this->resource->deniedReason,
            'registered_by' => $this->resource->registeredBy,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
