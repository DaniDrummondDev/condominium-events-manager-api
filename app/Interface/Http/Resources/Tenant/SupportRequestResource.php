<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Communication\DTOs\SupportRequestDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SupportRequestDTO $resource
 */
class SupportRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'user_id' => $this->resource->userId,
            'subject' => $this->resource->subject,
            'category' => $this->resource->category,
            'status' => $this->resource->status,
            'priority' => $this->resource->priority,
            'closed_at' => $this->resource->closedAt,
            'closed_reason' => $this->resource->closedReason,
            'created_at' => $this->resource->createdAt,
            'updated_at' => $this->resource->updatedAt,
        ];
    }
}
