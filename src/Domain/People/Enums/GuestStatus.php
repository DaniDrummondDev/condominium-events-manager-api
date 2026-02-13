<?php

declare(strict_types=1);

namespace Domain\People\Enums;

enum GuestStatus: string
{
    case Registered = 'registered';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Denied = 'denied';
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
            self::Registered => [self::CheckedIn, self::Denied, self::NoShow],
            self::CheckedIn => [self::CheckedOut],
            self::CheckedOut, self::Denied, self::NoShow => [],
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::CheckedOut, self::Denied, self::NoShow], true);
    }

    public function isPresent(): bool
    {
        return $this === self::CheckedIn;
    }

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registrado',
            self::CheckedIn => 'Presente',
            self::CheckedOut => 'Saiu',
            self::Denied => 'Acesso Negado',
            self::NoShow => 'Não Compareceu',
        };
    }
}
