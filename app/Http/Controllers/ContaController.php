<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\SaveAccount;
use App\Enums\RiotRegion;
use App\Http\Requests\ContaRequest;
use App\Models\Conta;
use App\Models\Season;
use App\Models\User;
use App\Queries\AccountAnalytics;
use App\Queries\AccountDecay;
use App\Queries\AccountRankHistory;
use App\Queries\AccountRanking;
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
    public function index(Request $request, AccountRanking $ranking, RankProgress $progress, AccountAnalytics $analytics): View
    {
        $days = $this->days($request);
        $season = app(SelectedSeason::class)->execute($request);
        $filters = $analytics->filters($request);
        $contas = $ranking->ordered($request->user()->contas())->with('latestSync')->get();

        return view('dashboard', [
            ...$analytics->build($request->user(), $season, $days, $filters),
            'season' => $season, 'seasons' => Season::where('status', '!=', 'draft')->orderByDesc('id')->get(),
            'rankProgress' => $progress->accounts($contas),
            'accountDecay' => app(AccountDecay::class)->accounts($contas),
            'rankHistories' => ($filters['tab'] ?? '') === 'progress' ? app(AccountRankHistory::class)->accounts($contas, $season, $days) : collect(),
            'accountNames' => $contas->pluck('riot_id', 'id'),
            'aggregate' => true, 'analyticsRoute' => 'dashboard', 'analyticsParams' => [],
            'contas' => $contas, 'days' => $days,
        ]);
    }

    public function show(Request $request, int $conta, RankProgress $progress, AccountAnalytics $analytics): View
    {
        $conta = $this->owned($request, $conta);
        $days = $this->days($request);
        $season = app(SelectedSeason::class)->execute($request);
        $filters = $analytics->filters($request);
        $region = RiotRegion::tryFrom($conta->regiao);
        $snapshots = ($filters['tab'] ?? '') === 'progress' ? $conta->rankSnapshots()->where('observed_at', '>=', $region ? $season->startFor($region) : $season->starts_at)
            ->when($season->ends_at, fn ($q) => $q->where('observed_at', '<', $season->ends_at))
            ->when($days > 0, fn ($q) => $q->where('observed_at', '>=', now()->subDays($days)))
            ->latest('observed_at')->limit(60)->get()->reverse() : collect();

        return view('contas.show', [
            ...$analytics->build($request->user(), $season, $days, $filters, $conta),
            'season' => $season, 'seasons' => Season::where('status', '!=', 'draft')->orderByDesc('id')->get(),
            'rankChart' => PersonalCharts::ranking($snapshots),
            'progress' => $progress->accounts(collect([$conta]))->get($conta->id),
            'decay' => app(AccountDecay::class)->accounts(collect([$conta]))->get($conta->id),
            'aggregate' => false, 'analyticsRoute' => 'contas.show', 'analyticsParams' => ['conta' => $conta],
            'snapshots' => $snapshots,
            'conta' => $conta->load('latestSync'), 'days' => $days,
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
