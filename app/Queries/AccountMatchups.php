<?php

namespace App\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccountMatchups
{
    public function grouped(Builder $base): Collection
    {
        return (clone $base)->whereNotNull('opponent_champion_name')
            ->select(['lane', 'opponent_champion_name'])
            ->selectRaw('MAX(opponent_champion_id) AS champion_id, COUNT(*) AS games, SUM(CASE WHEN win = 1 THEN 1 ELSE 0 END) AS wins, SUM(kills) AS kills, SUM(deaths) AS deaths, SUM(assists) AS assists, AVG(CASE WHEN duration_seconds > 0 THEN cs * 60.0 / duration_seconds END) AS cs_per_minute')
            ->groupBy('lane', 'opponent_champion_name')->orderByDesc('games')->orderBy('opponent_champion_name')
            ->get()->map(function ($row) {
                return ['role' => $row->lane, 'champion_name' => $row->opponent_champion_name,
                    'champion_id' => $row->champion_id, 'games' => (int) $row->games,
                    'wins' => (int) $row->wins, 'losses' => (int) $row->games - (int) $row->wins,
                    'winrate' => round($row->wins / $row->games * 100, 1),
                    'kda' => $row->deaths ? number_format(($row->kills + $row->assists) / $row->deaths, 2) : 'Perfeito',
                    'cs_per_minute' => $row->cs_per_minute];
            })->groupBy('role');
    }
}
