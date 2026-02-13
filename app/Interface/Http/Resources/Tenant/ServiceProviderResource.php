<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\People\DTOs\ServiceProviderDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ServiceProviderDTO $resource
 */
class ServiceProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'company_name' => $this->resource->companyName,
            'name' => $this->resource->name,
            'document' => $this->resource->document,
            'phone' => $this->resource->phone,
            'service_type' => $this->resource->serviceType,
            'status' => $this->resource->status,
            'notes' => $this->resource->notes,
            'created_by' => $this->resource->createdBy,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
