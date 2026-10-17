<?php

namespace App\Queries;

use App\Models\Partida;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlayerHistory
{
    public function encounters(User $user)
    {
        return Partida::query()->join('contas', 'contas.id', '=', 'partidas.conta_id')
            ->join('match_participants as players', 'players.riot_match_id', '=', 'partidas.riot_match_id')
            ->where('contas.user_id', $user->id)->whereColumn('partidas.identity_puuid', 'contas.puuid')
            ->where('partidas.queue_id', 420)->where('partidas.is_remake', false)
            ->whereIn('partidas.team_id', [100, 200])->whereIn('players.team_id', [100, 200])
            ->whereNotNull('players.puuid')->where('players.puuid', '!=', '')
            ->whereColumn('players.puuid', '!=', 'partidas.identity_puuid');
    }

    public function notes(User $user, iterable $matches): array
    {
        $matches = collect($matches);
        $puuids = $matches->flatMap(fn ($match) => $match->game?->participants->pluck('puuid') ?? []);

        return [
            'matchNotes' => DB::table('match_notes')->where('user_id', $user->id)->whereIn('match_id', $matches->pluck('match_id'))->pluck('body', 'match_id'),
            'playerNotes' => DB::table('player_notes')->where('user_id', $user->id)->whereIn('puuid', $puuids)->pluck('body', 'puuid'),
        ];
    }
}
