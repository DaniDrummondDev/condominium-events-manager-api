<?php

declare(strict_types=1);

namespace Domain\Communication\Enums;

enum ClosedReason: string
{
    case Resolved = 'resolved';
    case AutoClosed = 'auto_closed';
    case AdminClosed = 'admin_closed';

    public function label(): string
    {
        return match ($this) {
            self::Resolved => 'Resolvida',
            self::AutoClosed => 'Fechada Automaticamente',
            self::AdminClosed => 'Fechada pelo Administrador',
        };
    }
}
