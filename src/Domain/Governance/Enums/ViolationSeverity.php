<?php

declare(strict_types=1);

namespace Domain\Governance\Enums;

enum ViolationSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baixa',
            self::Medium => 'Média',
            self::High => 'Alta',
            self::Critical => 'Crítica',
        };
    }

    public function isEscalatable(): bool
    {
        return $this !== self::Critical;
    }
}
