<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Unit\DTOs\UnitDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property UnitDTO $resource
 */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'block_id' => $this->resource->blockId,
            'number' => $this->resource->number,
            'floor' => $this->resource->floor,
            'type' => $this->resource->type,
            'status' => $this->resource->status,
            'is_occupied' => $this->resource->isOccupied,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
