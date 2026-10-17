<?php

namespace App\Queries;

use App\Enums\RiotRegion;
use App\Models\RankSnapshot;
use App\Models\Season;
use App\Support\PersonalCharts;
use Illuminate\Support\Collection;

class AccountRankHistory
{
    public function accounts(Collection $accounts, Season $season, int $days): Collection
    {
        $snapshots = RankSnapshot::query()->join('contas', 'contas.id', '=', 'rank_snapshots.conta_id')
            ->whereIn('contas.id', $accounts->pluck('id'))->where('rank_snapshots.queue_id', 420)
            ->where(function ($query) use ($season) {
                foreach (RiotRegion::cases() as $region) {
                    $query->orWhere(fn ($q) => $q->where('contas.regiao', $region->value)->where('observed_at', '>=', $season->startFor($region)));
                }
            })
            ->when($season->ends_at, fn ($q) => $q->where('observed_at', '<', $season->ends_at))
            ->when($days > 0, fn ($q) => $q->where('observed_at', '>=', now()->subDays($days)))
            ->select('rank_snapshots.*')->orderByDesc('observed_at')->orderByDesc('rank_snapshots.id')->groupLimit(60, 'rank_snapshots.conta_id')->get();
        $groups = [];
        foreach ($snapshots as $snapshot) {
            if (count($groups[$snapshot->conta_id] ?? []) < 60) {
                $groups[$snapshot->conta_id][] = $snapshot;
            }
        }

        return $accounts->map(fn ($account) => ['account' => $account,
            'points' => PersonalCharts::ranking(collect(array_reverse($groups[$account->id] ?? [])))]);
    }
}
