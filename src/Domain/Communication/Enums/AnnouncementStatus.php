<?php

declare(strict_types=1);

namespace Domain\Communication\Enums;

enum AnnouncementStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions(), true);
    }

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published],
            self::Published => [self::Archived],
            self::Archived => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Archived;
    }

    public function isVisible(): bool
    {
        return $this === self::Published;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Published => 'Publicado',
            self::Archived => 'Arquivado',
        };
    }
}
