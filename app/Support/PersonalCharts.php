<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PersonalCharts
{
    public static function daily(array $days, int $limit = 21): array
    {
        return collect($days)->map(fn ($day, $date) => [
            'label' => substr($date, 0, 5), 'title' => $date,
            'wins' => (int) $day['wins'], 'losses' => (int) $day['games'] - (int) $day['wins'],
            'games' => (int) $day['games'],
        ])->values()->take(-$limit)->values()->all();
    }

    public static function ranking(Collection $snapshots): array
    {
        return $snapshots->filter(fn ($snapshot) => $snapshot->chart_value !== null)->values()
            ->map(fn ($snapshot) => ['value' => $snapshot->chart_value,
                'label' => $snapshot->observed_at->timezone('America/Sao_Paulo')->format('d/m H:i'),
                'title' => $snapshot->label])->all();
    }
}
