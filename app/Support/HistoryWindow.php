<?php

namespace App\Support;

use App\Enums\RiotRegion;
use App\Models\Season;

class HistoryWindow
{
    public static function forRegion(RiotRegion $region, ?Season $season = null): array
    {
        $season ??= Season::current();

        return [
            'season' => $season->name, 'season_id' => $season->id,
            'start' => $season->startFor($region)->getTimestamp(),
            'end' => min(now()->getTimestamp(), $season->ends_at?->getTimestamp() ?? PHP_INT_MAX),
        ];
    }

    public static function matchStartedAtMs(array $info): ?int
    {
        if (is_numeric($info['gameStartTimestamp'] ?? null)) {
            return (int) $info['gameStartTimestamp'];
        }
        if (is_numeric($info['gameCreation'] ?? null)) {
            return (int) $info['gameCreation'];
        }
        if (is_numeric($info['gameEndTimestamp'] ?? null) && is_int($info['gameDuration'] ?? null)) {
            return (int) $info['gameEndTimestamp'] - $info['gameDuration'] * 1000;
        }

        return null;
    }

    public static function contains(array $info, array $window): bool
    {
        $start = self::matchStartedAtMs($info);

        return $start !== null && $start >= $window['start'] * 1000 && $start < $window['end'] * 1000;
    }
}
