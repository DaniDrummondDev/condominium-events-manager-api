<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Reservation\DTOs\ReservationDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ReservationDTO $resource
 */
class ReservationDetailResource extends JsonResource
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
            'approved_by' => $this->resource->approvedBy,
            'approved_at' => $this->resource->approvedAt,
            'rejected_by' => $this->resource->rejectedBy,
            'rejected_at' => $this->resource->rejectedAt,
            'rejection_reason' => $this->resource->rejectionReason,
            'canceled_by' => $this->resource->canceledBy,
            'canceled_at' => $this->resource->canceledAt,
            'cancellation_reason' => $this->resource->cancellationReason,
            'completed_at' => $this->resource->completedAt,
            'no_show_at' => $this->resource->noShowAt,
            'no_show_by' => $this->resource->noShowBy,
            'checked_in_at' => $this->resource->checkedInAt,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
