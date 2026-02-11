<?php

declare(strict_types=1);

namespace Domain\Governance\Enums;

enum ViolationType: string
{
    case NoShow = 'no_show';
    case LateCancellation = 'late_cancellation';
    case CapacityExceeded = 'capacity_exceeded';
    case NoiseComplaint = 'noise_complaint';
    case Damage = 'damage';
    case RuleViolation = 'rule_violation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NoShow => 'Não Comparecimento',
            self::LateCancellation => 'Cancelamento Tardio',
            self::CapacityExceeded => 'Capacidade Excedida',
            self::NoiseComplaint => 'Reclamação de Barulho',
            self::Damage => 'Dano',
            self::RuleViolation => 'Violação de Regra',
            self::Other => 'Outro',
        };
    }

    public function isAutomatic(): bool
    {
        return in_array($this, [self::NoShow, self::LateCancellation], true);
    }
}
