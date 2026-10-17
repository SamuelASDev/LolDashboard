<?php

namespace App\Actions\Sync;

use App\Enums\RiotRegion;
use App\Jobs\SyncAccount;
use App\Models\Conta;
use App\Models\Season;
use App\Models\SyncRun;
use App\Models\User;
use App\Support\HistoryWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartSync
{
    public function execute(Conta $conta, string $mode = 'recent', int $queueId = 420, ?Season $season = null): SyncRun
    {
        if ($queueId !== 420 || ! in_array($mode, ['recent', 'full'], true)) {
            throw ValidationException::withMessages(['sync' => 'Somente Solo/Duo e modos recent/full sao permitidos.']);
        }

        if (! in_array(config('queue.connections.'.config('riot.queue_connection').'.driver'), ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            throw ValidationException::withMessages(['sync' => 'Configure uma fila assíncrona para sincronizar.']);
        }

        return DB::transaction(function () use ($conta, $mode, $queueId, $season) {
            User::whereKey($conta->user_id)->lockForUpdate()->firstOrFail();
            $conta = Conta::whereKey($conta->id)->lockForUpdate()->firstOrFail();
            $active = $conta->syncRuns()->whereNotNull('active_conta_id')->first();
            if ($active) {
                return $active;
            }
            $runs = SyncRun::whereHas('conta', fn ($q) => $q->where('user_id', $conta->user_id));
            if ((clone $runs)->whereNotNull('active_conta_id')->count() >= config('workspace.max_active_syncs')
                || (clone $runs)->where('mode', $mode)->where('created_at', '>=', now()->startOfDay())->count() >= config('workspace.daily_'.$mode.'_syncs')) {
                throw ValidationException::withMessages(['sync' => 'Limite de sincronizacoes atingido. Aguarde as tarefas atuais terminarem ou tente no proximo dia.']);
            }
            $region = RiotRegion::tryFrom($conta->regiao);
            if (! $region) {
                throw ValidationException::withMessages(['sync' => 'Região não suportada. Corrija o cadastro antes de sincronizar.']);
            }
            $season = Season::whereKey(($season ?? Season::current())->id)->lockForUpdate()->firstOrFail();
            if ($season->status === 'draft') {
                throw ValidationException::withMessages(['season' => 'Esta temporada ainda não começou.']);
            }
            $run = $conta->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion($region, $season)], 'mode' => $mode, 'queue_id' => $queueId, 'active_conta_id' => $conta->id, 'message' => 'Aguardando o worker.']);
            SyncAccount::dispatch($run->id, $run->version)->onConnection(config('riot.queue_connection'))
                ->onQueue(config('riot.queue'))->afterCommit();

            return $run;
        });
    }
}
