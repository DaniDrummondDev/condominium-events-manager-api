<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Unit\DTOs\BlockDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property BlockDTO $resource
 */
class BlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'identifier' => $this->resource->identifier,
            'floors' => $this->resource->floors,
            'status' => $this->resource->status,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
