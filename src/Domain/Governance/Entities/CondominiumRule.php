<?php

declare(strict_types=1);

namespace Domain\Governance\Entities;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;

class CondominiumRule
{
    public function __construct(
        private readonly Uuid $id,
        private string $title,
        private string $description,
        private string $category,
        private bool $isActive,
        private int $order,
        private readonly Uuid $createdBy,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        Uuid $id,
        string $title,
        string $description,
        string $category,
        int $order,
        Uuid $createdBy,
    ): self {
        return new self(
            id: $id,
            title: $title,
            description: $description,
            category: $category,
            isActive: true,
            order: $order,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable,
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function createdBy(): Uuid
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updateTitle(string $title): void
    {
        $this->title = $title;
    }

    public function updateDescription(string $description): void
    {
        $this->description = $description;
    }

    public function updateCategory(string $category): void
    {
        $this->category = $category;
    }

    public function updateOrder(int $order): void
    {
        $this->order = $order;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }
}
