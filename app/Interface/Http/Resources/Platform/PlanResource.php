<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Platform;

use Application\Billing\DTOs\PlanDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PlanDTO $resource
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'status' => $this->resource->status,
            'current_version' => $this->resource->currentVersion ? [
                'id' => $this->resource->currentVersion->id,
                'version' => $this->resource->currentVersion->version,
                'status' => $this->resource->currentVersion->status,
                'created_at' => $this->resource->currentVersion->createdAt,
                'prices' => array_map(fn ($p) => [
                    'id' => $p->id,
                    'billing_cycle' => $p->billingCycle,
                    'price_in_cents' => $p->priceInCents,
                    'currency' => $p->currency,
                    'trial_days' => $p->trialDays,
                ], $this->resource->currentVersion->prices),
            ] : null,
            'features' => $this->resource->features,
        ];
    }
}
