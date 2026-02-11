<?php

declare(strict_types=1);

namespace App\Interface\Http\Resources\Platform;

use Application\Billing\DTOs\SubscriptionDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SubscriptionDTO $resource
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'tenant_id' => $this->resource->tenantId,
            'plan_version_id' => $this->resource->planVersionId,
            'status' => $this->resource->status,
            'billing_cycle' => $this->resource->billingCycle,
            'current_period_start' => $this->resource->currentPeriodStart,
            'current_period_end' => $this->resource->currentPeriodEnd,
            'grace_period_end' => $this->resource->gracePeriodEnd,
            'canceled_at' => $this->resource->canceledAt,
        ];
    }
}
