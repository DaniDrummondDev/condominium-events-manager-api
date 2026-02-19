<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Platform;

use Domain\Tenant\Entities\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Tenant $resource
 */
class TenantRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tenant_slug' => $this->resource->slug(),
            'status' => $this->resource->status()->value,
            'message' => 'Tenant registration accepted. Provisioning in progress.',
        ];
    }
}
