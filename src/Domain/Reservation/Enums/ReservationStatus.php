<?php

declare(strict_types=1);

namespace Domain\Reservation\Enums;

enum ReservationStatus: string
{
    case PendingApproval = 'pending_approval';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Canceled = 'canceled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
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
            self::PendingApproval => [self::Confirmed, self::Rejected, self::Canceled],
            self::Confirmed => [self::Canceled, self::InProgress],
            self::InProgress => [self::Completed, self::NoShow],
            self::Rejected, self::Canceled, self::Completed, self::NoShow => [],
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::PendingApproval, self::Confirmed, self::InProgress], true);
    }

    public function isPending(): bool
    {
        return $this === self::PendingApproval;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Canceled, self::Completed, self::NoShow], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Aguardando Aprovação',
            self::Confirmed => 'Confirmada',
            self::Rejected => 'Rejeitada',
            self::Canceled => 'Cancelada',
            self::InProgress => 'Em Andamento',
            self::Completed => 'Concluída',
            self::NoShow => 'Não Compareceu',
        };
    }
}
