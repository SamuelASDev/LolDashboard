<?php

namespace App\Queries;

use App\Models\Partida;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PersonalWorkspace
{
    public function read(User $user): array
    {
        $favorites = DB::table('filter_favorites')->where('user_id', $user->id)->orderByDesc('id')->get();
        $goals = DB::table('personal_goals')->where('user_id', $user->id)->orderByDesc('id')->get();
        foreach ($goals as $goal) {
            $base = Partida::join('contas', 'contas.id', '=', 'partidas.conta_id')->where('contas.user_id', $user->id)
                ->whereColumn('identity_puuid', 'contas.puuid')->where('queue_id', 420)->where('is_remake', false)
                ->where('played_at', '>=', $goal->starts_on.' 00:00:00')->where('played_at', '<', CarbonImmutable::parse($goal->ends_on)->addDay());
            $row = $base->selectRaw('COUNT(*) as games, COALESCE(SUM(CASE WHEN win = 1 THEN 1 ELSE 0 END), 0) as wins')->first();
            $goal->value = match ($goal->metric) {
                'wins' => $row->wins, 'winrate' => $row->games ? round($row->wins / $row->games * 100, 1) : 0, default => $row->games,
            };
            $goal->games = $row->games;
        }
        $notes = DB::table('player_notes')->where('user_id', $user->id)->orderByDesc('updated_at')->paginate(20, ['*'], 'notes_page');

        return compact('favorites', 'goals', 'notes');
    }
}
