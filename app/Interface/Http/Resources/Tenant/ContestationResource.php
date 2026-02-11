<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Governance\DTOs\ContestationDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ContestationDTO $resource
 */
class ContestationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'violation_id' => $this->resource->violationId,
            'tenant_user_id' => $this->resource->tenantUserId,
            'reason' => $this->resource->reason,
            'status' => $this->resource->status,
            'response' => $this->resource->response,
            'responded_by' => $this->resource->respondedBy,
            'responded_at' => $this->resource->respondedAt,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
