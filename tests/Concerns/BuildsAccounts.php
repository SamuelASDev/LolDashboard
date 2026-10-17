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
        return ['info' => array_merge([
            'queueId' => 420, 'gameEndTimestamp' => now()->getTimestampMs(), 'gameDuration' => 1800,
            'participants' => [array_merge(['puuid' => $puuid, 'championName' => 'Ahri', 'win' => true,
                'kills' => 5, 'deaths' => 2, 'assists' => 7, 'individualPosition' => 'MIDDLE'], $player)],
        ], $info)];
    }
}
