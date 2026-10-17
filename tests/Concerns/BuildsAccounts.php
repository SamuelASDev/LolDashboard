<?php

namespace Tests\Concerns;

use App\Models\Conta;
use App\Models\Partida;
use App\Models\User;

trait BuildsAccounts
{
    protected function account(?User $user = null, array $attributes = []): Conta
    {
        $user ??= User::factory()->create();
        $conta = $user->contas()->make(['nick' => 'Principal', 'riot_id' => 'Jogador#BR1', 'regiao' => 'br1']);
        $conta->forceFill($attributes)->save();

        return $conta;
    }

    protected function matchRecord(Conta $conta, array $attributes = []): Partida
    {
        return $conta->partidas()->create(array_merge([
            'match_id' => 'BR1_'.fake()->unique()->numberBetween(1, 9999999),
            'champion_name' => 'Ahri', 'win' => true, 'kills' => 5, 'deaths' => 2,
            'assists' => 7, 'lane' => 'MIDDLE', 'played_at' => now(),
            'identity_puuid' => $conta->puuid, 'queue_id' => 420, 'is_remake' => false,
        ], $attributes));
    }

    protected function matchPayload(string $puuid = 'puuid-1', array $player = [], array $info = []): array
    {
        $payload = ['info' => array_merge([
            'queueId' => 420, 'gameEndTimestamp' => now()->getTimestampMs(), 'gameDuration' => 1800,
            'participants' => [array_merge(['participantId' => 1, 'teamId' => 100, 'puuid' => $puuid, 'championName' => 'Ahri', 'win' => true,
                'kills' => 5, 'deaths' => 2, 'assists' => 7, 'individualPosition' => 'MIDDLE'], $player)],
        ], $info)];
        if (! array_key_exists('participants', $info)) {
            foreach (range(2, 10) as $slot) {
                $payload['info']['participants'][] = ['participantId' => $slot, 'puuid' => 'fixture-'.$slot,
                    'teamId' => $slot <= 5 ? 100 : 200, 'championName' => 'Garen', 'win' => $slot <= 5,
                    'kills' => 0, 'deaths' => 0, 'assists' => 0, 'teamPosition' => 'UNKNOWN'];
            }
        }

        return $payload;
    }
}
