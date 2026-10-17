<?php

namespace App\Queries;

use App\Models\Conta;
use App\Models\Partida;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccountStatistics
{
    public function base(User $user, int $days = 30, ?Conta $conta = null, int $queueId = 420, ?Season $season = null): Builder
    {
        $query = Partida::query()->join('contas', 'contas.id', '=', 'partidas.conta_id')
            ->where('contas.user_id', $user->id)
            ->whereColumn('partidas.identity_puuid', 'contas.puuid')
            ->where('partidas.queue_id', $queueId)->where('partidas.is_remake', false)
            ->when($days > 0, fn ($q) => $q->where('played_at', '>=', now()->subDays($days)))
            ->when($conta, fn ($q) => $q->where('contas.id', $conta->id));

        return $season ? $season->constrainMatches($query) : $query;
    }

    public function summary(Builder $base): array
    {
        $row = (clone $base)->selectRaw('COUNT(*) AS games, COALESCE(SUM(CASE WHEN win = 1 THEN 1 ELSE 0 END),0) AS wins, COALESCE(SUM(kills),0) AS kills, COALESCE(SUM(deaths),0) AS deaths, COALESCE(SUM(assists),0) AS assists')->first();

        return $this->metrics($row->getAttributes());
    }

    public function champions(Builder $base, bool $byAccount = false): Collection
    {
        $group = $byAccount ? ['partidas.conta_id', 'champion_name'] : ['champion_name'];

        return (clone $base)->select($group)->selectRaw('COUNT(*) AS games, SUM(CASE WHEN win = 1 THEN 1 ELSE 0 END) AS wins, SUM(kills) AS kills, SUM(deaths) AS deaths, SUM(assists) AS assists, MAX(champion_id) AS champion_id, AVG(CASE WHEN duration_seconds > 0 THEN cs * 60.0 / duration_seconds END) AS cs_per_minute, AVG(CASE WHEN duration_seconds > 0 THEN gold * 60.0 / duration_seconds END) AS gold_per_minute, AVG(CASE WHEN duration_seconds > 0 THEN damage * 60.0 / duration_seconds END) AS damage_per_minute, AVG(kill_participation) AS participation, AVG(vision_score) AS vision, COUNT(details_synced_at) AS detailed')
            ->groupBy($group)->orderByDesc('games')->orderBy('champion_name')->get()
            ->map(fn ($row) => $this->metrics($row->getAttributes()) + [
                'champion_id' => $row->champion_id, 'cs_per_minute' => $row->cs_per_minute,
                'gold_per_minute' => $row->gold_per_minute, 'damage_per_minute' => $row->damage_per_minute,
                'participation' => $row->participation, 'vision' => $row->vision, 'detailed' => $row->detailed,
                'champion_name' => $row->champion_name, 'conta_id' => $row->conta_id,
            ]);
    }

    private function metrics(array $values): array
    {
        $games = (int) $values['games'];
        $wins = (int) $values['wins'];
        $kills = (int) $values['kills'];
        $deaths = (int) $values['deaths'];
        $assists = (int) $values['assists'];

        return [
            'games' => $games, 'wins' => $wins, 'losses' => $games - $wins,
            'kills' => $kills, 'deaths' => $deaths, 'assists' => $assists,
            'winrate' => $games ? round($wins / $games * 100, 1) : 0,
            'kda' => $deaths > 0 ? number_format(($kills + $assists) / $deaths, 2) : ($games ? 'Perfeito' : '—'),
            'avg_kills' => $games ? round($kills / $games, 1) : 0,
            'avg_deaths' => $games ? round($deaths / $games, 1) : 0,
            'avg_assists' => $games ? round($assists / $games, 1) : 0,
        ];
    }
}
