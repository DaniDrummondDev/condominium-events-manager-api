<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Space\DTOs\SpaceBlockDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SpaceBlockDTO $resource
 */
class SpaceBlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'space_id' => $this->resource->spaceId,
            'reason' => $this->resource->reason,
            'start_datetime' => $this->resource->startDatetime,
            'end_datetime' => $this->resource->endDatetime,
            'blocked_by' => $this->resource->blockedBy,
            'notes' => $this->resource->notes,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
