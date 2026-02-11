<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Space\DTOs\SpaceDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SpaceDTO $resource
 */
class SpaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'type' => $this->resource->type,
            'status' => $this->resource->status,
            'capacity' => $this->resource->capacity,
            'requires_approval' => $this->resource->requiresApproval,
            'max_duration_hours' => $this->resource->maxDurationHours,
            'max_advance_days' => $this->resource->maxAdvanceDays,
            'min_advance_hours' => $this->resource->minAdvanceHours,
            'cancellation_deadline_hours' => $this->resource->cancellationDeadlineHours,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
