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
                'price_in_cents' => $this->resource->currentVersion->priceInCents,
                'currency' => $this->resource->currentVersion->currency,
                'billing_cycle' => $this->resource->currentVersion->billingCycle,
                'trial_days' => $this->resource->currentVersion->trialDays,
                'status' => $this->resource->currentVersion->status,
                'created_at' => $this->resource->currentVersion->createdAt,
            ] : null,
            'features' => $this->resource->features,
        ];
    }
}
