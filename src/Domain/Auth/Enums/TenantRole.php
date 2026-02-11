<?php

declare(strict_types=1);

namespace Domain\Auth\Enums;

enum TenantRole: string
{
    case Sindico = 'sindico';
    case Administradora = 'administradora';
    case Condomino = 'condomino';
    case Funcionario = 'funcionario';

    public function requiresMfa(): bool
    {
        return match ($this) {
            self::Sindico, self::Administradora => true,
            default => false,
        };
    }
}
