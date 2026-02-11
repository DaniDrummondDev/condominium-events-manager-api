<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Governance\DTOs\PenaltyDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PenaltyDTO $resource
 */
class PenaltyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'violation_id' => $this->resource->violationId,
            'unit_id' => $this->resource->unitId,
            'type' => $this->resource->type,
            'starts_at' => $this->resource->startsAt,
            'ends_at' => $this->resource->endsAt,
            'status' => $this->resource->status,
            'revoked_at' => $this->resource->revokedAt,
            'revoked_by' => $this->resource->revokedBy,
            'revoked_reason' => $this->resource->revokedReason,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
