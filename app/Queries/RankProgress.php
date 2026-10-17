<?php

namespace App\Queries;

use App\Models\Conta;
use App\Models\RankCutoff;
use Illuminate\Support\Collection;

class RankProgress
{
    private const TIERS = ['IRON' => 'Ferro', 'BRONZE' => 'Bronze', 'SILVER' => 'Prata', 'GOLD' => 'Ouro',
        'PLATINUM' => 'Platina', 'EMERALD' => 'Esmeralda', 'DIAMOND' => 'Diamante', 'MASTER' => 'Mestre'];

    public function accounts(Collection $accounts): Collection
    {
        $cutoffs = RankCutoff::whereIn('region', $accounts->pluck('regiao')->unique())
            ->where('queue_id', 420)->get()->groupBy('region');

        return $accounts->mapWithKeys(fn (Conta $account) => [$account->id => $this->forAccount($account, $cutoffs->get($account->regiao, collect()))]);
    }

    public function forAccount(Conta $account, Collection $cutoffs): array
    {
        $tier = explode(' ', $account->elo ?? '')[0];
        $lp = max(0, $account->pdl ?? 0);
        if (! $account->rank_synced_at || $tier === '' || $tier === 'UNRANKED') {
            return ['bars' => [], 'message' => 'Progresso disponível após sincronizar uma classificação Solo/Duo.'];
        }
        if ($tier === 'CHALLENGER') {
            return ['bars' => [], 'message' => 'Você já está no elo mais alto: Desafiante.'];
        }
        if (in_array($tier, ['MASTER', 'GRANDMASTER'], true)) {
            $bars = [];
            foreach ([$tier === 'MASTER' ? 'GRANDMASTER' : 'CHALLENGER'] as $target) {
                $cutoff = $cutoffs->firstWhere('tier', $target);
                $targetLp = $cutoff?->lp;
                $bars[] = ['label' => 'Corte '.($target === 'GRANDMASTER' ? 'Grão-Mestre' : 'Desafiante'),
                    'current' => $lp, 'target' => $targetLp, 'percent' => $targetLp > 0 ? min(100, round($lp / $targetLp * 100, 1)) : null,
                    'remaining' => $targetLp !== null ? max(0, $targetLp - $lp) : null,
                    'margin' => $targetLp !== null ? max(0, $lp - $targetLp) : null,
                    'source_url' => $cutoff?->source_url, 'source_updated_at' => $cutoff?->source_updated_at,
                    'fetched_at' => $cutoff?->fetched_at, 'stale' => $cutoff?->isStale() ?? true, 'dynamic' => true];
            }

            return ['bars' => $bars, 'message' => 'Referência Solo/Duo; atingir o corte não garante promoção ou permanência.'];
        }
        $tiers = array_keys(self::TIERS);
        $index = array_search($tier, $tiers, true);
        $division = explode(' ', $account->elo)[1] ?? null;
        $offset = ['IV' => 0, 'III' => 100, 'II' => 200, 'I' => 300][$division] ?? null;
        if ($index === false || $offset === null || ! isset($tiers[$index + 1])) {
            return ['bars' => [], 'message' => 'Classificação indisponível para cálculo do progresso.'];
        }
        $current = min(400, $offset + $lp);

        return ['bars' => [['label' => 'Próximo elo: '.self::TIERS[$tiers[$index + 1]], 'current' => $current,
            'target' => 400, 'percent' => round($current / 400 * 100, 1), 'remaining' => 400 - $current,
            'margin' => 0, 'dynamic' => false, 'stale' => false]],
            'message' => 'Progresso Solo/Duo entre as divisões do elo atual.'];
    }
}
