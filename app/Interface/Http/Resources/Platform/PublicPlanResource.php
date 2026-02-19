<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Platform;

use Application\Billing\DTOs\PlanDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PlanDTO $resource
 */
class PublicPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'prices' => $this->resource->currentVersion
                ? array_map(fn ($p) => [
                    'billing_cycle' => $p->billingCycle,
                    'price_in_cents' => $p->priceInCents,
                    'currency' => $p->currency,
                    'trial_days' => $p->trialDays,
                ], $this->resource->currentVersion->prices)
                : [],
            'features' => array_map(fn ($f) => [
                'feature_key' => $f['feature_key'],
                'value' => $f['value'],
                'type' => $f['type'],
            ], $this->resource->features),
        ];
    }
}
