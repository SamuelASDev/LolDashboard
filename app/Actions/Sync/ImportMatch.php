<?php

namespace App\Actions\Sync;

use App\Enums\RiotRegion;
use App\Exceptions\RiotException;
use App\Models\Conta;
use App\Models\Partida;
use App\Models\RiotMatch;
use App\Models\Season;
use App\Support\HistoryWindow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ImportMatch
{
    public function execute(Conta $conta, string $matchId, array $data, ?array $window = null): bool
    {
        $info = $data['info'] ?? null;
        if (! is_array($info) || ! is_int($info['queueId'] ?? null)) {
            throw new RiotException('A Riot retornou informações incompletas de partida.');
        }
        if (isset($data['metadata']['matchId']) && $data['metadata']['matchId'] !== $matchId) {
            throw new RiotException('A Riot retornou uma partida diferente da solicitada.');
        }
        if (! in_array($info['queueId'], [420], true)) {
            return false;
        }
        $player = collect($info['participants'] ?? [])->firstWhere('puuid', $conta->puuid);
        if (! is_array($player)) {
            throw new RiotException('A identidade não foi encontrada na partida. Histórico preservado.');
        }
        foreach (['championName', 'win', 'kills', 'deaths', 'assists'] as $field) {
            if (! array_key_exists($field, $player)) {
                throw new RiotException('A Riot retornou uma partida incompleta.');
            }
        }
        if (! is_string($player['championName']) || ! is_bool($player['win'])
            || ! is_numeric($info['gameEndTimestamp'] ?? null)) {
            throw new RiotException('A Riot retornou dados de partida inválidos.');
        }
        foreach (['kills', 'deaths', 'assists'] as $field) {
            if (! is_int($player[$field]) || $player[$field] < 0) {
                throw new RiotException('A Riot retornou estatísticas inválidas.');
            }
        }
        if (! is_array($info['participants'] ?? null) || count($info['participants']) !== 10
            || (isset($info['gameDuration']) && (! is_int($info['gameDuration']) || $info['gameDuration'] < 0))) {
            throw new RiotException('A Riot retornou uma estrutura de partida inválida.');
        }
        if (isset($info['teams']) && ! is_array($info['teams'])) {
            throw new RiotException('A Riot retornou times inválidos.');
        }
        $slots = [];
        $identities = [];
        $teamCounts = [100 => 0, 200 => 0];
        foreach ($info['participants'] as $index => $participant) {
            if (! is_array($participant) || ! is_string($participant['championName'] ?? null)
                || ! is_bool($participant['win'] ?? null)) {
                throw new RiotException('A Riot retornou um participante inválido.');
            }
            if (isset($participant['perks']) && ! is_array($participant['perks'])) {
                throw new RiotException('A Riot retornou runas inválidas.');
            }
            $slot = $participant['participantId'] ?? $index + 1;
            if (! is_int($slot) || $slot < 1 || $slot > 10 || isset($slots[$slot])) {
                throw new RiotException('A Riot retornou participantes duplicados ou inválidos.');
            }
            $identity = $participant['puuid'] ?? null;
            $team = $participant['teamId'] ?? null;
            if (! is_string($identity) || $identity === '' || strlen($identity) > 128 || isset($identities[$identity]) || ! in_array($team, [100, 200], true)) {
                throw new RiotException('A Riot retornou identidades ou times invalidos. Historico preservado.');
            }
            $identities[$identity] = true;
            $teamCounts[$team]++;
            $slots[$slot] = true;
            foreach (['kills', 'deaths', 'assists', 'goldEarned', 'totalDamageDealtToChampions', 'totalDamageTaken',
                'visionScore', 'totalMinionsKilled', 'neutralMinionsKilled', 'wardsPlaced', 'wardsKilled',
                'visionWardsBoughtInGame', 'totalHeal', 'pentaKills', 'championId', 'teamId', 'champLevel',
                'item0', 'item1', 'item2', 'item3', 'item4', 'item5', 'item6', 'summoner1Id', 'summoner2Id'] as $field) {
                if (isset($participant[$field]) && (! is_int($participant[$field]) || $participant[$field] < 0)) {
                    throw new RiotException('A Riot retornou estatísticas inválidas de participante.');
                }
            }
        }
        if ($teamCounts !== [100 => 5, 200 => 5]) {
            throw new RiotException('A Riot retornou times incompletos. Historico preservado.');
        }
        $window ??= HistoryWindow::forRegion(RiotRegion::from($conta->regiao));
        if (HistoryWindow::matchStartedAtMs($info) === null) {
            throw new RiotException('A Riot não informou o horário de início da partida.');
        }
        if (! HistoryWindow::contains($info, $window)) {
            return false;
        }
        $playedAt = CarbonImmutable::createFromTimestampMs($info['gameEndTimestamp'])->utc();
        // A short match with the early-surrender flag is treated as a remake.
        // Longer early surrenders remain in the dataset; no equivalence with league totals is assumed.
        $remake = ($player['gameEndedInEarlySurrender'] ?? false) && ($info['gameDuration'] ?? 9999) < 300;

        return DB::transaction(function () use ($conta, $matchId, $data, $info, $player, $playedAt, $remake, $slots, $window) {
            $conta = Conta::whereKey($conta->id)->lockForUpdate()->firstOrFail();
            $season = isset($window['season_id'])
                ? Season::whereKey($window['season_id'])->lockForUpdate()->firstOrFail()
                : Season::where('name', (string) $window['season'])->lockForUpdate()->first();
            if ($season?->ends_at) {
                $window['end'] = min($window['end'], $season->ends_at->getTimestamp());
            }
            if (! HistoryWindow::contains($info, $window)) {
                return false;
            }
            $game = RiotMatch::updateOrCreate(['match_id' => $matchId], [
                'routing' => RiotRegion::from($conta->regiao)->routing(),
                'started_at' => isset($info['gameStartTimestamp']) ? CarbonImmutable::createFromTimestampMs($info['gameStartTimestamp'])->utc() : null,
                'queue_id' => $info['queueId'], 'duration_seconds' => $info['gameDuration'] ?? null,
                'played_at' => $playedAt, 'game_version' => $info['gameVersion'] ?? null,
                'teams' => $info['teams'] ?? [], 'payload' => $data, 'schema_version' => 1,
            ]);
            $game->participants()->whereNotIn('participant_id', array_keys($slots))->delete();
            foreach ($info['participants'] as $index => $participant) {
                $game->participants()->updateOrCreate(['participant_id' => $participant['participantId'] ?? $index + 1], [
                    'puuid' => $participant['puuid'] ?? null,
                    'riot_id' => ($participant['riotIdGameName'] ?? $participant['summonerName'] ?? 'Jogador').'#'.($participant['riotIdTagline'] ?? ''),
                    'team_id' => $participant['teamId'] ?? 0, 'champion_id' => $participant['championId'] ?? null,
                    'champion_name' => $participant['championName'] ?? 'Desconhecido',
                    'position' => $participant['teamPosition'] ?? $participant['individualPosition'] ?? 'UNKNOWN',
                    'win' => $participant['win'] ?? false, 'champion_level' => $participant['champLevel'] ?? null,
                    'kills' => $participant['kills'] ?? 0, 'deaths' => $participant['deaths'] ?? 0, 'assists' => $participant['assists'] ?? 0,
                    'items' => array_map(fn ($slot) => $participant['item'.$slot] ?? 0, range(0, 6)),
                    'spells' => [$participant['summoner1Id'] ?? 0, $participant['summoner2Id'] ?? 0],
                    'runes' => $participant['perks'] ?? [], 'stats' => collect($participant)->only([
                        'totalMinionsKilled', 'neutralMinionsKilled', 'goldEarned', 'totalDamageDealtToChampions',
                        'totalDamageTaken', 'visionScore', 'wardsPlaced', 'wardsKilled', 'visionWardsBoughtInGame',
                        'totalHeal', 'pentaKills', 'damageDealtToObjectives', 'turretKills',
                    ])->all(),
                ]);
            }
            $teamKills = collect($info['participants'])->where('teamId', $player['teamId'] ?? -1)->sum('kills');
            $position = $player['teamPosition'] ?? $player['individualPosition'] ?? 'UNKNOWN';
            $position = in_array($position, ['TOP', 'JUNGLE', 'MIDDLE', 'BOTTOM', 'UTILITY'], true) ? $position : 'UNKNOWN';
            $opponents = collect($info['participants'])->filter(fn ($p) => isset($player['teamId'], $p['teamId']) && $p['teamId'] !== $player['teamId']
                && $position !== 'UNKNOWN'
                && ($p['teamPosition'] ?? $p['individualPosition'] ?? null) === $position);
            $opponent = $opponents->count() === 1 ? $opponents->first() : null;
            $extra = [
                'riot_match_id' => $game->id, 'champion_id' => $player['championId'] ?? null,
                'team_id' => $player['teamId'] ?? null, 'duration_seconds' => $info['gameDuration'] ?? null,
                'cs' => isset($player['totalMinionsKilled'], $player['neutralMinionsKilled']) ? $player['totalMinionsKilled'] + $player['neutralMinionsKilled'] : null,
                'kill_participation' => $teamKills > 0 ? round(($player['kills'] + $player['assists']) / $teamKills * 100, 1) : null,
                'opponent_champion_id' => $opponent['championId'] ?? null, 'opponent_champion_name' => $opponent['championName'] ?? null,
                'ended_in_surrender' => $player['gameEndedInSurrender'] ?? null,
                'items' => array_map(fn ($slot) => $player['item'.$slot] ?? 0, range(0, 6)),
                'spells' => [$player['summoner1Id'] ?? 0, $player['summoner2Id'] ?? 0],
                'runes' => $player['perks'] ?? [], 'details_synced_at' => now(),
            ];
            foreach (['gold' => 'goldEarned', 'damage' => 'totalDamageDealtToChampions', 'damage_taken' => 'totalDamageTaken',
                'vision_score' => 'visionScore', 'wards_placed' => 'wardsPlaced', 'wards_killed' => 'wardsKilled',
                'control_wards' => 'visionWardsBoughtInGame', 'penta_kills' => 'pentaKills', 'healing' => 'totalHeal'] as $column => $source) {
                $extra[$column] = $player[$source] ?? null;
            }
            $match = Partida::updateOrCreate(['conta_id' => $conta->id, 'match_id' => $matchId], [
                'champion_name' => $player['championName'], 'win' => $player['win'],
                'kills' => $player['kills'], 'deaths' => $player['deaths'], 'assists' => $player['assists'],
                'lane' => $position,
                'started_at' => CarbonImmutable::createFromTimestampMs(HistoryWindow::matchStartedAtMs($info))->utc(),
                'played_at' => $playedAt, 'identity_puuid' => $conta->puuid,
                'queue_id' => $info['queueId'], 'is_remake' => $remake,
            ] + $extra);
            if ($info['queueId'] === 420 && (! $conta->last_game_at || $playedAt->gt($conta->last_game_at))) {
                $conta->forceFill(['last_game_at' => $playedAt])->save();
            }

            return $match->wasRecentlyCreated;
        });
    }
}
