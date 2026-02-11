<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Space\DTOs\SpaceDetailDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SpaceDetailDTO $resource
 */
class SpaceDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->space->id,
            'name' => $this->resource->space->name,
            'description' => $this->resource->space->description,
            'type' => $this->resource->space->type,
            'status' => $this->resource->space->status,
            'capacity' => $this->resource->space->capacity,
            'requires_approval' => $this->resource->space->requiresApproval,
            'max_duration_hours' => $this->resource->space->maxDurationHours,
            'max_advance_days' => $this->resource->space->maxAdvanceDays,
            'min_advance_hours' => $this->resource->space->minAdvanceHours,
            'cancellation_deadline_hours' => $this->resource->space->cancellationDeadlineHours,
            'created_at' => $this->resource->space->createdAt,
            'availabilities' => SpaceAvailabilityResource::collection($this->resource->availabilities),
            'blocks' => SpaceBlockResource::collection($this->resource->blocks),
            'rules' => SpaceRuleResource::collection($this->resource->rules),
        ];
    }
}
