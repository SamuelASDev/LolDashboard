<?php

namespace App\Actions\Sync;

use App\Enums\RiotRegion;
use App\Exceptions\RiotException;
use App\Models\Conta;
use App\Models\Partida;
use App\Models\RankSnapshot;
use App\Models\RiotMatch;
use App\Models\Season;
use App\Models\SyncRun;
use App\Queries\AccountDecay;
use App\Services\RiotService;
use App\Support\HistoryWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdvanceSync
{
    public function __construct(private RiotService $riot, private ImportMatch $importer) {}

    /** One bounded unit per job. No network is performed inside a database transaction. */
    public function execute(SyncRun $run): void
    {
        if ($run->queue_id !== 420) {
            $run->finish('cancelled', 'Fila desativada. Historico preservado.');

            return;
        }
        $conta = $run->conta;
        $region = RiotRegion::tryFrom($conta->regiao);
        if (! $region) {
            throw new RiotException('Região não suportada. Corrija o cadastro antes de sincronizar.');
        }
        $context = $run->context ?? [];
        if (! isset($context['history_window'])) {
            if ($run->cursor > 0 || ($run->pending_ids ?? []) !== []) {
                $run->finish('cancelled', 'Busca anterior encerrada: o período agora é a temporada selecionada. Inicie uma nova sincronização.');

                return;
            }
            $context['history_window'] = HistoryWindow::forRegion($region);
            $run->context = $context;
            $run->save();
        }
        $window = $run->context['history_window'];
        $season = isset($window['season_id'])
            ? Season::find($window['season_id'])
            : Season::where('name', (string) $window['season'])->first();
        if ($season?->ends_at) {
            $window['end'] = min($window['end'], $season->ends_at->getTimestamp());
            $context = $run->context;
            $context['history_window'] = $window;
            $run->context = $context;
            $run->save();
        }
        if ($window['end'] <= $window['start']) {
            $run->finish('completed', 'A temporada selecionada ainda não começou para este período.');

            return;
        }
        if ($run->phase === 'snapshot') {
            $account = $run->context['account'] ?? $this->riot->account($conta->riot_id, $region, $conta->puuid);
            $run->context = array_merge($run->context ?? [], ['account' => $account]);
            $run->save();
            if ($conta->puuid && $account['puuid'] !== $conta->puuid) {
                throw new RiotException('A identidade da conta mudou. Histórico preservado.');
            }
            $rank = $this->riot->rank($account['puuid'], $region);
            $total = $rank ? $rank['wins'] + $rank['losses'] : 0;
            $canonical = isset($account['gameName'], $account['tagLine'])
                ? $account['gameName'].'#'.$account['tagLine'] : $conta->riot_id;
            try {
                DB::transaction(function () use ($conta, $account, $rank, $total, $canonical, $run) {
                    $conta = Conta::whereKey($conta->id)->lockForUpdate()->firstOrFail();
                    $conta->forceFill([
                        'puuid' => $account['puuid'], 'riot_id' => $canonical,
                        'identity_key' => Conta::identityKey($conta->user_id, $conta->regiao, $canonical),
                        'elo' => $rank ? $rank['tier'].' '.$rank['rank'] : 'UNRANKED',
                        'pdl' => $rank['leaguePoints'] ?? 0, 'wins' => $rank['wins'] ?? 0,
                        'losses' => $rank['losses'] ?? 0,
                        'winrate' => $total ? round($rank['wins'] / $total * 100, 1) : 0,
                        'cutoff_lp' => null, 'rank_synced_at' => now(),
                        'decay_reference' => in_array($rank['tier'] ?? '', AccountDecay::TIERS, true) ? $conta->decay_reference : null,
                        'last_game_at' => $conta->puuid ? $conta->last_game_at : null,
                    ])->save();
                    RankSnapshot::create(['conta_id' => $conta->id, 'queue_id' => 420,
                        'tier' => $rank['tier'] ?? 'UNRANKED', 'division' => $rank['rank'] ?? null,
                        'lp' => $rank['leaguePoints'] ?? 0, 'wins' => $rank['wins'] ?? 0,
                        'losses' => $rank['losses'] ?? 0, 'observed_at' => now()]);
                    $context = $run->context;
                    unset($context['account']);
                    $run->context = $context;
                    $run->phase = 'history';
                    $run->message = 'Ranking atualizado. Importando partidas da temporada selecionada.';
                    $run->save();
                });
            } catch (UniqueConstraintViolationException) {
                throw new RiotException('Esta identidade Riot já pertence a outro cadastro seu nesta região.');
            }

            return;
        }
        $pending = $run->pending_ids ?? [];
        if ($pending === []) {
            if ($run->stop_after_page || ($run->mode === 'recent' && $run->cursor >= 60)) {
                $coverage = ['history_synced_at' => now()];
                if ($run->queue_id === 420 && $season?->id === Season::current()->id && $window['end'] >= now()->subDay()->getTimestamp()) {
                    $bounded = $window;
                    // Only dates actually traversed by this run attest recent coverage.
                    $bounded['start'] = max($window['start'], $run->context['coverage_start'] ?? now()->getTimestamp());
                    $coverage += ['solo_history_synced_at' => now(), 'solo_history_window' => $bounded];
                }
                $conta->forceFill($coverage)->save();
                $run->finish('completed', 'Histórico recente da temporada selecionada atualizado. Use a importação da temporada para ampliar a busca.');

                return;
            }
            if ($run->cursor >= config('riot.max_pages') * config('riot.page_size')) {
                $run->finish('partial', 'Limite de páginas atingido. O histórico disponível pode estar incompleto.');

                return;
            }
            $ids = $this->riot->matchIds($conta->puuid, $region, $run->cursor, $run->queue_id, $window);
            if ($ids === []) {
                $coverage = ['history_synced_at' => now()];
                if ($run->queue_id === 420 && $season?->id === Season::current()->id && $window['end'] >= now()->subDay()->getTimestamp()) {
                    $coverage += ['solo_history_synced_at' => now(), 'solo_history_window' => $window];
                }
                $conta->forceFill($coverage)->save();
                $run->finish('completed', 'Busca da temporada selecionada concluída. Não há mais partidas disponíveis neste período.');

                return;
            }
            $known = Partida::where('conta_id', $conta->id)->where('identity_puuid', $conta->puuid)
                ->whereNotNull('details_synced_at')->where('played_at', '>=', CarbonImmutable::createFromTimestamp($window['start'], 'UTC'))->whereIn('match_id', $ids)->pluck('match_id')->all();
            if ($known !== []) {
                $oldest = Partida::where('conta_id', $conta->id)->whereIn('match_id', $known)
                    ->selectRaw('MIN(COALESCE(started_at, played_at)) as oldest')->value('oldest');
                if ($oldest) {
                    $context = $run->context;
                    $context['coverage_start'] = min($context['coverage_start'] ?? PHP_INT_MAX, CarbonImmutable::parse($oldest)->getTimestamp());
                    $run->context = $context;
                }
            }
            $pageIds = $ids;
            sort($pageIds);
            $hash = hash('sha256', implode('|', $pageIds));
            $seen = $run->context['seen_pages'] ?? [];
            $allKnown = count($known) === count($ids);
            if (isset($seen[$hash]) && ! ($run->mode === 'recent' && $allKnown)) {
                $run->finish('partial', 'A Riot repetiu uma página sem avançar. Busca interrompida; partidas já importadas foram preservadas.');

                return;
            }
            $seen[$hash] = true;
            $run->context = array_merge($run->context, ['seen_pages' => $seen]);
            $run->stop_after_page = $run->mode === 'recent' && count($known) === count($ids);
            $run->pending_ids = array_values(array_diff($ids, $known));
            $run->processed += count($ids) - count($run->pending_ids);
            $run->cursor += count($ids);
            $run->message = 'Página de histórico recebida.';

            return;
        }
        $id = $pending[0];
        $known = Partida::where('conta_id', $conta->id)->where('match_id', $id)
            ->where('identity_puuid', $conta->puuid)->whereNotNull('details_synced_at')->where('played_at', '>=', CarbonImmutable::createFromTimestamp($window['start'], 'UTC'))->exists();
        $gameLock = Cache::lock('riot:match:'.hash('sha256', $id), 90);
        if (! $gameLock->get()) {
            throw new RiotException('Partida sendo importada por outra sincronização.', true, 2, true);
        }
        try {
            $cached = RiotMatch::where('match_id', $id)->first();
            $data = $known ? null : ($cached?->payload ?? $this->riot->match($id, $region));
            if ($data !== null && ($data['info']['queueId'] ?? null) !== $run->queue_id) {
                throw new RiotException('A Riot retornou uma partida de outra fila. Histórico preservado.');
            }
            $info = ($data ?? $cached?->payload)['info'] ?? [];
            if (HistoryWindow::matchStartedAtMs($info) !== null && ! HistoryWindow::contains($info, $window)) {
                $run->finish('partial', 'A Riot retornou partida fora da temporada selecionada ou do período fixado. Busca interrompida; dados confirmados preservados.');

                return;
            }
            DB::transaction(function () use ($conta, $id, $data, $run, $pending, $window) {
                if ($data !== null && $this->importer->execute($conta, $id, $data, $window)) {
                    $run->imported++;
                }
                if ($data !== null) {
                    $context = $run->context;
                    $context['coverage_start'] = min($context['coverage_start'] ?? PHP_INT_MAX, (int) floor(HistoryWindow::matchStartedAtMs($data['info']) / 1000));
                    $run->context = $context;
                }
                array_shift($pending);
                $run->pending_ids = $pending;
                $run->processed++;
                $run->message = 'Histórico em processamento.';
                // Commit match and checkpoint together so a crash cannot lose progress.
                $run->save();
            });
        } finally {
            $gameLock->release();
        }
    }
}
