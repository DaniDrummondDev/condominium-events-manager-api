<?php

declare(strict_types=1);

namespace Domain\Billing\Entities;

use Domain\Billing\Enums\FeatureType;
use Domain\Shared\ValueObjects\Uuid;

class Feature
{
    public function __construct(
        private readonly Uuid $id,
        private readonly string $code,
        private string $name,
        private readonly FeatureType $type,
        private ?string $description = null,
    ) {}

    public function id(): Uuid
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): FeatureType
    {
        return $this->type;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function updateDescription(?string $description): void
    {
        $this->description = $description;
    }
}
