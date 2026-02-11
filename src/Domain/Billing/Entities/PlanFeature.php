<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use Domain\Billing\Enums\FeatureType;
use Domain\Shared\ValueObjects\Uuid;

class PlanFeature
{
    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $planVersionId,
        private readonly string $featureKey,
        private readonly string $value,
        private readonly FeatureType $type,
    ) {}

    public function id(): Uuid
    {
        return $this->id;
    }

    public function planVersionId(): Uuid
    {
        return $this->planVersionId;
    }

    public function featureKey(): string
    {
        return $this->featureKey;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function type(): FeatureType
    {
        return $this->type;
    }
}
