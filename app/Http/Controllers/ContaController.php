<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\SaveAccount;
use App\Enums\RiotRegion;
use App\Http\Requests\ContaRequest;
use App\Models\Conta;
use App\Models\Season;
use App\Models\User;
use App\Queries\AccountInsights;
use App\Queries\AccountMatchups;
use App\Queries\AccountRanking;
use App\Queries\AccountStatistics;
use App\Queries\RankProgress;
use App\Queries\SelectedSeason;
use App\Support\PersonalCharts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContaController extends Controller
{
    public function index(Request $request, AccountStatistics $stats, AccountRanking $ranking, RankProgress $progress): View
    {
        $days = $this->days($request);
        $season = app(SelectedSeason::class)->execute($request);
        $contas = $ranking->ordered($request->user()->contas())->with('latestSync')->get();
        $base = $stats->base($request->user(), $days, null, 420, $season);

        return view('dashboard', [
            'season' => $season, 'seasons' => Season::where('status', '!=', 'draft')->orderByDesc('id')->get(),
            'rankProgress' => $progress->accounts($contas),
            'contas' => $contas, 'days' => $days, 'summary' => $stats->summary($base),
            'champions' => $stats->champions($base),
            'topByAccount' => $stats->champions($base, true)->groupBy('conta_id'),
        ]);
    }

    public function show(Request $request, int $conta, AccountStatistics $stats, AccountInsights $insights, RankProgress $progress, AccountMatchups $matchups): View
    {
        $conta = $this->owned($request, $conta);
        $days = $this->days($request);
        $season = app(SelectedSeason::class)->execute($request);
        $base = $stats->base($request->user(), $days, $conta, 420, $season);

        $filters = $request->validate([
            'queue' => ['sometimes', 'integer', 'in:420,440'],
            'opponent' => ['nullable', 'string', 'max:64'],
            'champion' => ['nullable', 'string', 'max:64'], 'role' => ['nullable', 'in:TOP,JUNGLE,MIDDLE,BOTTOM,UTILITY,UNKNOWN'],
            'result' => ['nullable', 'in:win,loss'], 'tab' => ['nullable', 'in:overview,history,champions,matchups,patterns,records,progress'],
        ]);
        if ((int) ($filters['queue'] ?? 420) === 440) {
            $base = $stats->base($request->user(), $days, $conta, 440, $season);
        }
        $optionBase = clone $base;
        $base->when($filters['champion'] ?? null, fn ($q, $value) => $q->where('champion_name', $value))
            ->when($filters['opponent'] ?? null, fn ($q, $value) => $q->where('opponent_champion_name', $value))
            ->when($filters['role'] ?? null, fn ($q, $value) => $q->where('lane', $value))
            ->when($filters['result'] ?? null, fn ($q, $value) => $q->where('win', $value === 'win'));
        $insight = $insights->build($base);

        $region = RiotRegion::tryFrom($conta->regiao);
        $snapshots = $conta->rankSnapshots()->where('observed_at', '>=', $region ? $season->startFor($region) : $season->starts_at)
            ->when($season->ends_at, fn ($q) => $q->where('observed_at', '<', $season->ends_at))
            ->when($days > 0, fn ($q) => $q->where('observed_at', '>=', now()->subDays($days)))
            ->latest('observed_at')->limit(60)->get()->reverse();

        return view('contas.show', [
            'season' => $season, 'seasons' => Season::where('status', '!=', 'draft')->orderByDesc('id')->get(),
            'matchups' => ($filters['tab'] ?? 'overview') === 'matchups' ? $matchups->grouped($base) : collect(),
            'dailyChart' => PersonalCharts::daily($insight['days']),
            'rankChart' => PersonalCharts::ranking($snapshots),
            'progress' => $progress->accounts(collect([$conta]))->get($conta->id),
            'insight' => $insight, 'tab' => $filters['tab'] ?? 'overview',
            'championOptions' => (clone $optionBase)->distinct()->orderBy('champion_name')->pluck('champion_name'),
            'opponentOptions' => (clone $optionBase)->whereNotNull('opponent_champion_name')->distinct()->orderBy('opponent_champion_name')->pluck('opponent_champion_name'),
            'snapshots' => $snapshots,
            'conta' => $conta->load('latestSync'), 'days' => $days,
            'summary' => $stats->summary($base), 'champions' => $stats->champions($base),
            'partidas' => (clone $base)->select('partidas.*')->with(['game' => fn ($q) => $q->forDisplay(), 'game.participants'])->orderByDesc('played_at')->orderByDesc('partidas.id')->paginate(20)->withQueryString(),
            'legacyCount' => $conta->partidas()->whereNull('identity_puuid')->count(),
        ]);
    }

    public function create(): View
    {
        return view('contas.create');
    }

    public function store(ContaRequest $request, SaveAccount $save): RedirectResponse
    {
        $save->execute($request->user(), $request->validated());

        return to_route('dashboard')->with('success', 'Conta cadastrada. Sincronize para confirmar a identidade e importar os dados.');
    }

    public function edit(Request $request, int $conta): View
    {
        return view('contas.edit', ['conta' => $this->owned($request, $conta)]);
    }

    public function update(ContaRequest $request, int $conta, SaveAccount $save): RedirectResponse
    {
        $conta = $this->owned($request, $conta);
        Gate::authorize('update', $conta);
        $save->execute($request->user(), $request->validated(), $conta);

        return to_route('dashboard')->with('success', 'Cadastro atualizado.');
    }

    public function destroy(Request $request, int $conta): RedirectResponse
    {
        $conta = $this->owned($request, $conta);
        Gate::authorize('delete', $conta);
        DB::transaction(function () use ($request, $conta) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $conta = $request->user()->contas()->whereKey($conta->id)->lockForUpdate()->firstOrFail();
            abort_if($conta->syncRuns()->whereNotNull('active_conta_id')->exists(), 409, 'Cancele a sincronização antes de excluir.');
            $conta->delete();
        });

        return to_route('dashboard')->with('success', 'Conta e histórico removidos.');
    }

    private function owned(Request $request, int $id): Conta
    {
        $conta = $request->user()->contas()->findOrFail($id);
        Gate::authorize('view', $conta);

        return $conta;
    }

    private function days(Request $request): int
    {
        $validated = $request->validate(['days' => ['sometimes', 'integer', 'in:0,7,30,90,365']]);

        return (int) ($validated['days'] ?? 30);
    }
}
