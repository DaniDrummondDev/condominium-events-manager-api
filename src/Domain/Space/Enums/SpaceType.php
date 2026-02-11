<?php

declare(strict_types=1);

namespace Domain\Space\Enums;

enum SpaceType: string
{
    case PartyHall = 'party_hall';
    case Bbq = 'bbq';
    case Pool = 'pool';
    case Gym = 'gym';
    case Playground = 'playground';
    case SportsCourt = 'sports_court';
    case MeetingRoom = 'meeting_room';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PartyHall => 'Salão de Festas',
            self::Bbq => 'Churrasqueira',
            self::Pool => 'Piscina',
            self::Gym => 'Academia',
            self::Playground => 'Playground',
            self::SportsCourt => 'Quadra Esportiva',
            self::MeetingRoom => 'Sala de Reunião',
            self::Other => 'Outro',
        };
    }
}
