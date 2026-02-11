<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Unit\DTOs\ResidentDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ResidentDTO $resource
 */
class ResidentResource extends JsonResource
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
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'role_in_unit' => $this->resource->roleInUnit,
            'is_primary' => $this->resource->isPrimary,
            'status' => $this->resource->status,
            'moved_in_at' => $this->resource->movedInAt,
            'moved_out_at' => $this->resource->movedOutAt,
        ];
    }
}
