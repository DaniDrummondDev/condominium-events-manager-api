<?php

declare(strict_types=1);

namespace Domain\Tenant\Enums;

enum CondominiumType: string
{
    case Horizontal = 'horizontal';
    case Vertical = 'vertical';
    case Mixed = 'mixed';
}
