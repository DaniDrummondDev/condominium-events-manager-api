<?php

declare(strict_types=1);

namespace Domain\Space\Entities;

use Domain\Shared\ValueObjects\Uuid;

class SpaceRule
{
    public function __construct(
        private readonly Uuid $id,
        private readonly Uuid $spaceId,
        private readonly string $ruleKey,
        private string $ruleValue,
        private ?string $description,
    ) {}

    public static function create(
        Uuid $id,
        Uuid $spaceId,
        string $ruleKey,
        string $ruleValue,
        ?string $description,
    ): self {
        return new self($id, $spaceId, $ruleKey, $ruleValue, $description);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function spaceId(): Uuid
    {
        return $this->spaceId;
    }

    public function ruleKey(): string
    {
        return $this->ruleKey;
    }

    public function ruleValue(): string
    {
        return $this->ruleValue;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function updateValue(string $ruleValue): void
    {
        $this->ruleValue = $ruleValue;
    }

    public function updateDescription(?string $description): void
    {
        $this->description = $description;
    }
}
