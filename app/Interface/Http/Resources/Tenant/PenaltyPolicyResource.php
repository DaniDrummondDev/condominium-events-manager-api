<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Governance\DTOs\PenaltyPolicyDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PenaltyPolicyDTO $resource
 */
class PenaltyPolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'violation_type' => $this->resource->violationType,
            'occurrence_threshold' => $this->resource->occurrenceThreshold,
            'penalty_type' => $this->resource->penaltyType,
            'block_days' => $this->resource->blockDays,
            'is_active' => $this->resource->isActive,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
