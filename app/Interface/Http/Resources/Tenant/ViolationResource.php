<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Governance\DTOs\ViolationDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ViolationDTO $resource
 */
class ViolationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'unit_id' => $this->resource->unitId,
            'type' => $this->resource->type,
            'severity' => $this->resource->severity,
            'status' => $this->resource->status,
            'is_automatic' => $this->resource->isAutomatic,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
