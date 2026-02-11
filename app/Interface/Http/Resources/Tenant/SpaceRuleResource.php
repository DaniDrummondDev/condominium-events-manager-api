<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Tenant;

use Application\Space\DTOs\SpaceRuleDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SpaceRuleDTO $resource
 */
class SpaceRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'space_id' => $this->resource->spaceId,
            'rule_key' => $this->resource->ruleKey,
            'rule_value' => $this->resource->ruleValue,
            'description' => $this->resource->description,
        ];
    }
}
