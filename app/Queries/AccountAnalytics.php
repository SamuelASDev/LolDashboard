<?php

namespace App\Queries;

use App\Models\Conta;
use App\Models\Season;
use App\Models\User;
use App\Support\PersonalCharts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AccountAnalytics
{
    public function __construct(private AccountStatistics $stats, private AccountInsights $insights, private AccountMatchups $matchups) {}

    public function filters(Request $request): array
    {
        return $request->validate([
            'queue' => ['sometimes', 'integer', 'in:420'],
            'opponent' => ['nullable', 'string', 'max:64'],
            'champion' => ['nullable', 'string', 'max:64'], 'role' => ['nullable', 'in:TOP,JUNGLE,MIDDLE,BOTTOM,UTILITY,UNKNOWN'],
            'result' => ['nullable', 'in:win,loss'], 'tab' => ['nullable', 'in:overview,history,champions,matchups,patterns,records,progress'],
        ]);
    }

    public function build(User $user, Season $season, int $days, array $filters, ?Conta $account = null): array
    {
        $base = $this->stats->base($user, $days, $account, (int) ($filters['queue'] ?? 420), $season);
        $options = clone $base;
        $base->when($filters['champion'] ?? null, fn ($q, $value) => $q->where('champion_name', $value))
            ->when($filters['opponent'] ?? null, fn ($q, $value) => $q->where('opponent_champion_name', $value))
            ->when($filters['role'] ?? null, fn ($q, $value) => $q->where('lane', $value))
            ->when($filters['result'] ?? null, fn ($q, $value) => $q->where('win', $value === 'win'));
        $tab = $filters['tab'] ?? 'overview';
        // A short private cache uses a database signature so bulk updates/deletes also invalidate it.
        $signature = (clone $base)->selectRaw('COUNT(*) as n, MAX(partidas.updated_at) as latest, COALESCE(SUM(partidas.id),0) as ids, MAX(partidas.played_at) as last_game')->first()->getAttributes();
        $key = 'analytics:'.hash('sha256', json_encode([$user->id, $account?->id, $season->toArray(), $days, $filters, $signature, Cache::get('analytics:revision'), now()->format('YmdHi')]));
        $insight = Cache::remember($key, 60, fn () => $this->insights->build($base,
            includeOpponents: $tab === 'patterns', scan: ! in_array($tab, ['champions', 'matchups', 'history'], true), includeTeammates: $tab === 'patterns'));

        $matches = ($tab === 'history' ? clone $base : (clone $base)->whereRaw('1 = 0'))->select('partidas.*')->with(['conta:id,nick,riot_id', 'game' => fn ($q) => $q->forDisplay(), 'game.participants'])
            ->orderByDesc('played_at')->orderByDesc('partidas.id')->paginate(20)->withQueryString();
        if ($tab === 'history') {
            $insight['recent'] = (clone $base)->select('partidas.*')->orderByDesc('played_at')->orderByDesc('partidas.id')->limit(20)->get()->reverse()->values()->all();
        }
        $notes = app(PlayerHistory::class)->notes($user, $matches->items());

        if ($tab === 'patterns') {
            $puuids = collect($insight['teammates'])->merge($insight['allOpponents'])->merge($insight['laneOpponents'])->pluck('puuid');
            $notes['playerNotes'] = DB::table('player_notes')->where('user_id', $user->id)->whereIn('puuid', $puuids)->pluck('body', 'puuid');
        }

        return $notes + [
            'tab' => $tab, 'insight' => $insight, 'summary' => $this->stats->summary($base),
            'champions' => $this->stats->champions($base),
            'topByAccount' => $account ? collect() : $this->stats->champions($base, true)->groupBy('conta_id'),
            'dailyChart' => PersonalCharts::daily($insight['days']),
            'matchups' => $tab === 'matchups' ? $this->matchups->grouped($base) : collect(),
            'championOptions' => (clone $options)->distinct()->orderBy('champion_name')->pluck('champion_name'),
            'opponentOptions' => (clone $options)->whereNotNull('opponent_champion_name')->distinct()->orderBy('opponent_champion_name')->pluck('opponent_champion_name'),
            'partidas' => $matches,
        ];
    }
}
