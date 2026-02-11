<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Governance\DTOs\RuleDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property RuleDTO $resource
 */
class CondominiumRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'category' => $this->resource->category,
            'is_active' => $this->resource->isActive,
            'order' => $this->resource->order,
            'created_by' => $this->resource->createdBy,
            'created_at' => $this->resource->createdAt,
        ];
    }
}
