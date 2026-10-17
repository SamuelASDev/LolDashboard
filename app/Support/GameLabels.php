<?php

namespace App\Support;

final class GameLabels
{
    public const QUEUES = [420 => 'Solo/Duo'];

    public const ROLES = ['TOP' => 'Topo', 'JUNGLE' => 'Selva', 'MIDDLE' => 'Meio',
        'BOTTOM' => 'Atirador', 'UTILITY' => 'Suporte', 'UNKNOWN' => 'Não identificada'];

    public static function duration(?int $seconds): string
    {
        return $seconds ? intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT) : '—';
    }

    public static function number(?float $value, int $decimals = 0): string
    {
        return $value === null ? '—' : number_format($value, $decimals, ',', '.');
    }
}
