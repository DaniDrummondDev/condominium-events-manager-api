<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Governance\DTOs\ViolationDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ViolationDTO $resource
 */
class ViolationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'unit_id' => $this->resource->unitId,
            'tenant_user_id' => $this->resource->tenantUserId,
            'reservation_id' => $this->resource->reservationId,
            'rule_id' => $this->resource->ruleId,
            'type' => $this->resource->type,
            'severity' => $this->resource->severity,
            'description' => $this->resource->description,
            'status' => $this->resource->status,
            'is_automatic' => $this->resource->isAutomatic,
            'created_by' => $this->resource->createdBy,
            'upheld_by' => $this->resource->upheldBy,
            'upheld_at' => $this->resource->upheldAt,
            'revoked_by' => $this->resource->revokedBy,
            'revoked_at' => $this->resource->revokedAt,
            'revoked_reason' => $this->resource->revokedReason,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
