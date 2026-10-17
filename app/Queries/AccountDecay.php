<?php

namespace App\Queries;

use App\Enums\RiotRegion;
use App\Models\Conta;
use App\Models\Partida;
use App\Models\Season;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Projeção local: a API utilizada não fornece o saldo de dias do cliente. */
class AccountDecay
{
    public const TIERS = ['MASTER', 'GRANDMASTER', 'CHALLENGER'];

    public const SOURCE = 'https://support.riotgames.com/en-us/league-of-legends/gameplay/placements-promotions-series-demotions-and-decay';

    public static function applies(Conta $account): bool
    {
        return $account->puuid && $account->rank_synced_at
            && in_array(explode(' ', $account->elo ?? '')[0], self::TIERS, true);
    }

    /** Recebe somente contas já autorizadas pelo controller; uma consulta de partidas em lote. */
    public function accounts(Collection $accounts): Collection
    {
        $now = CarbonImmutable::now('UTC')->startOfSecond();
        $season = Season::current();
        $states = [];
        foreach ($accounts as $account) {
            if (! self::applies($account)) {
                continue;
            }
            $region = RiotRegion::tryFrom($account->regiao);
            if (! $region) {
                continue;
            }
            $reference = $account->decay_reference;
            $manual = $reference && ($reference['puuid'] ?? null) === $account->puuid
                && ($reference['region'] ?? null) === $account->regiao
                && ($reference['season_id'] ?? null) === $season->id;
            $at = $manual ? CarbonImmutable::parse($reference['observed_at'], 'UTC') : $now->subDays(14);
            $at = $at->max($season->startFor($region));
            $days = $manual ? (int) $reference['days'] : 0;
            $states[$account->id] = ['account' => $account, 'at' => $at, 'manual' => (bool) $manual,
                'lower' => $at->addDays($days), 'upper' => $at->addDays($manual ? $days : 14), 'games' => 0];
        }
        if ($states === []) {
            return collect();
        }
        $from = collect($states)->pluck('at')->min();
        // Identidade vinculada à própria conta, sem misturar Flex, legado, remakes ou filtros de tela.
        $matches = Partida::query()->join('contas', 'contas.id', '=', 'partidas.conta_id')
            ->whereIn('contas.id', array_keys($states))->whereColumn('partidas.identity_puuid', 'contas.puuid')
            ->where('partidas.queue_id', 420)->where('partidas.is_remake', false)
            ->whereNotNull('partidas.details_synced_at')->where('partidas.played_at', '>', $from)
            ->where('partidas.played_at', '<=', $now)
            ->select('partidas.conta_id', 'partidas.played_at')
            ->orderBy('partidas.played_at')->orderBy('partidas.id')->cursor();
        foreach ($matches as $match) {
            $state = &$states[$match->conta_id];
            $played = CarbonImmutable::instance($match->played_at);
            if ($played->lessThanOrEqualTo($state['at'])) {
                unset($state);

                continue;
            }
            foreach (['lower', 'upper'] as $bound) {
                // Uma partida acrescenta um dia; nunca reinicia automaticamente uma reserva de 14.
                $state[$bound] = $state[$bound]->max($played)->addDay()->min($played->addDays(14));
            }
            $state['games']++;
            unset($state);
        }

        return collect($states)->map(function ($state) use ($now, $season) {
            $lower = max(0, $state['lower']->getTimestamp() - $now->getTimestamp());
            $upper = max(0, $state['upper']->getTimestamp() - $now->getTimestamp());
            $status = $upper === 0 ? 'possible_decay' : ($lower === 0 ? 'uncertain' : ($lower <= 2 * 86400 ? 'warning' : 'protected'));
            $account = $state['account'];

            return ['status' => $status, 'lower' => $lower, 'upper' => $upper,
                'remaining' => $lower === $upper ? self::duration($lower) : self::duration($lower).' a '.self::duration($upper),
                'deadline' => $upper > 0 ? $state['upper'] : null, 'manual' => $state['manual'],
                'reference_at' => $state['at'], 'games' => $state['games'],
                'stale' => ! $account->solo_history_synced_at || $account->solo_history_synced_at->lt($now->subDay())
                    || ($account->solo_history_window['season_id'] ?? null) !== $season->id
                    || ($account->solo_history_window['start'] ?? PHP_INT_MAX) > $now->subDays(14)->max($season->startFor(RiotRegion::from($account->regiao)))->getTimestamp()
                    || ($account->solo_history_window['end'] ?? 0) < $now->subDay()->getTimestamp()
                    || $account->rank_synced_at->lt($now->subDay()),
                'percent' => round(min(100, $lower / (14 * 86400) * 100), 1)];
        });
    }

    private static function duration(int $seconds): string
    {
        if ($seconds === 0) {
            return '0 dias';
        }
        if ($seconds < 3600) {
            return 'menos de 1 h';
        }
        $hours = intdiv($seconds, 3600);

        return $hours >= 24 ? intdiv($hours, 24).' d '.($hours % 24).' h' : $hours.' h';
    }
}
