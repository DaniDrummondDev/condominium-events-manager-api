<?php

declare(strict_types=1);

namespace Domain\People\Enums;

enum ServiceProviderVisitStatus: string
{
    case Scheduled = 'scheduled';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Canceled = 'canceled';
    case NoShow = 'no_show';

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
            self::Scheduled => [self::CheckedIn, self::Canceled, self::NoShow],
            self::CheckedIn => [self::CheckedOut],
            self::CheckedOut, self::Canceled, self::NoShow => [],
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::CheckedOut, self::Canceled, self::NoShow], true);
    }

    public function isPresent(): bool
    {
        return $this === self::CheckedIn;
    }

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::CheckedIn => 'Presente',
            self::CheckedOut => 'Saiu',
            self::Canceled => 'Cancelado',
            self::NoShow => 'Não Compareceu',
        };
    }
}
