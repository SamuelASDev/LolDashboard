<?php

namespace App\Enums;

enum RiotRegion: string
{
    case BR = 'br1';
    case NA = 'na1';
    case EUW = 'euw1';
    case EUNE = 'eun1';
    case KR = 'kr';
    case JP = 'jp1';

    public function routing(): string
    {
        return match ($this) {
            self::BR, self::NA => 'americas',
            self::EUW, self::EUNE => 'europe',
            self::KR, self::JP => 'asia',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::BR => 'Brasil', self::NA => 'América do Norte',
            self::EUW => 'Europa Ocidental', self::EUNE => 'Europa Nórdica e Oriental',
            self::KR => 'Coreia', self::JP => 'Japão',
        };
    }
}
