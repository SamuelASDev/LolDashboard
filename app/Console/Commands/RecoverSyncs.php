<?php

namespace App\Console\Commands;

use App\Jobs\SyncAccount;
use App\Models\SyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RecoverSyncs extends Command
{
    protected $signature = 'riot:recover';

    protected $description = 'Reagenda etapas inativas há 5 minutos sem reiniciar o histórico.';

    public function handle(): int
    {
        $count = 0;
        SyncRun::whereNotNull('active_conta_id')->where('updated_at', '<', now()->subMinutes(5))
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->eachById(function (SyncRun $run) use (&$count) {
                $lock = Cache::lock('riot:run:'.$run->id, 120);
                if (! $lock->get()) {
                    return;
                }
                try {
                    $run->refresh();
                    if (! $run->isActive() || $run->updated_at->gt(now()->subMinutes(5))) {
                        return;
                    }
                    if ($run->created_at->addHours(config('riot.run_hours'))->isPast()) {
                        $run->finish('failed', 'Tempo máximo atingido. Inicie uma nova sincronização.');

                        return;
                    }
                    SyncAccount::dispatch($run->id, $run->version)->onConnection(config('riot.queue_connection'))
                        ->onQueue(config('riot.queue'));
                    $run->touch();
                    $count++;
                } finally {
                    $lock->release();
                }
            });
        $this->info($count.' etapa(s) reagendada(s).');

        return self::SUCCESS;
    }
}
