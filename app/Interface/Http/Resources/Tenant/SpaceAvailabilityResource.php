<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Space\DTOs\SpaceAvailabilityDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SpaceAvailabilityDTO $resource
 */
class SpaceAvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'space_id' => $this->resource->spaceId,
            'day_of_week' => $this->resource->dayOfWeek,
            'start_time' => $this->resource->startTime,
            'end_time' => $this->resource->endTime,
        ];
    }
}
