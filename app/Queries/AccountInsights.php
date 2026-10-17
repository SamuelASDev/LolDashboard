<?php

namespace App\Queries;

use App\Models\Partida;
use App\Support\GameLabels;
use Illuminate\Database\Eloquent\Builder;

class AccountInsights
{
    public function build(Builder $base): array
    {
        $groups = ['side' => [], 'role' => [], 'duration' => [], 'day' => [], 'hour' => [], 'after' => [], 'opponent' => [], 'surrender' => []];
        $records = [];
        $recent = [];
        $days = [];
        $previous = null;
        $streak = $bestWins = $bestLosses = $total = $enriched = 0;
        $sums = $counts = [];
        foreach ((clone $base)->select('partidas.*')->orderBy('played_at')->orderBy('partidas.id')->cursor() as $match) {
            $total++;
            $enriched += $match->details_synced_at !== null ? 1 : 0;
            $date = $match->played_at->timezone('America/Sao_Paulo');
            $days[$date->format('d/m/Y')]['games'] = ($days[$date->format('d/m/Y')]['games'] ?? 0) + 1;
            $days[$date->format('d/m/Y')]['wins'] = ($days[$date->format('d/m/Y')]['wins'] ?? 0) + (int) $match->win;
            $dimensions = ['role' => GameLabels::ROLES[$match->lane] ?? $match->lane,
                'day' => ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][$date->dayOfWeek],
                'hour' => $date->format('H').':00',
                'side' => [100 => 'Azul', 200 => 'Vermelho'][$match->team_id] ?? null,
                'duration' => $match->duration_seconds === null ? null : ($match->duration_seconds < 1200 ? 'Até 20 min' : ($match->duration_seconds < 1800 ? '20–30 min' : ($match->duration_seconds < 2400 ? '30–40 min' : '40+ min'))),
                'after' => $previous === null ? null : ($previous ? 'Após vitória' : 'Após derrota'),
                'surrender' => $match->ended_in_surrender === null ? null : ($match->ended_in_surrender ? 'Terminou em rendição' : 'Sem rendição'),
                'opponent' => $match->opponent_champion_name];
            foreach ($dimensions as $dimension => $label) {
                if ($label === null) {
                    continue;
                }
                $groups[$dimension][$label]['games'] = ($groups[$dimension][$label]['games'] ?? 0) + 1;
                $groups[$dimension][$label]['wins'] = ($groups[$dimension][$label]['wins'] ?? 0) + (int) $match->win;
            }
            $streak = $previous === $match->win ? $streak + 1 : 1;
            if ($match->win) {
                $bestWins = max($bestWins, $streak);
            } else {
                $bestLosses = max($bestLosses, $streak);
            }
            $previous = $match->win;
            foreach (['cs', 'gold', 'damage', 'damage_taken', 'vision_score', 'wards_placed', 'wards_killed', 'control_wards', 'kill_participation', 'healing'] as $metric) {
                if ($match->$metric === null) {
                    continue;
                }
                $sums[$metric] = ($sums[$metric] ?? 0) + $match->$metric;
                $counts[$metric] = ($counts[$metric] ?? 0) + 1;
                if (($records[$metric]['value'] ?? -1) < $match->$metric) {
                    $records[$metric] = ['value' => $match->$metric, 'match' => $match];
                }
            }
            foreach (['kills', 'assists', 'penta_kills'] as $metric) {
                if ($match->$metric !== null && ($records[$metric]['value'] ?? -1) < $match->$metric) {
                    $records[$metric] = ['value' => $match->$metric, 'match' => $match];
                }
            }
            foreach (['cs', 'gold', 'damage'] as $metric) {
                if ($match->$metric === null || ! $match->duration_seconds) {
                    continue;
                }
                $key = $metric.'_per_minute';
                $sums[$key] = ($sums[$key] ?? 0) + $match->$metric * 60 / $match->duration_seconds;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
            $recent[] = $match;
            if (count($recent) > 20) {
                array_shift($recent);
            }
        }
        $averages = [];
        foreach ($sums as $key => $sum) {
            $averages[$key] = ['value' => round($sum / $counts[$key], 1), 'sample' => $counts[$key]];
        }
        $teammates = Partida::query()
            ->whereIn('partidas.id', (clone $base)->select('partidas.id'))
            ->join('match_participants as teammates', function ($join) {
                $join->on('teammates.riot_match_id', '=', 'partidas.riot_match_id')
                    ->on('teammates.team_id', '=', 'partidas.team_id')
                    ->whereColumn('teammates.puuid', '!=', 'partidas.identity_puuid');
            })
            ->select('teammates.puuid')->selectRaw('MAX(teammates.riot_id) AS riot_id, COUNT(*) AS games, SUM(CASE WHEN partidas.win = 1 THEN 1 ELSE 0 END) AS wins')
            ->groupBy('teammates.puuid')->orderByDesc('games')->orderBy('teammates.puuid')->limit(10)->get();

        return compact('teammates', 'groups', 'records', 'recent', 'days', 'total', 'enriched', 'averages', 'bestWins', 'bestLosses', 'streak', 'previous');
    }
}
