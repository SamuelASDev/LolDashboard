<?php

namespace App\Jobs;

use App\Actions\Sync\AdvanceSync;
use App\Exceptions\RiotException;
use App\Models\SyncRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAccount implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId, public int $version) {}

    public function handle(AdvanceSync $advance): void
    {
        $lock = Cache::lock('riot:run:'.$this->runId, 120);
        if (! $lock->get()) {
            $this->release(5);

            return;
        }
        try {
            $run = SyncRun::with('conta')->find($this->runId);
            if (! $run || ! $run->isActive() || $run->version !== $this->version) {
                return;
            }
            if ($run->created_at->addHours(config('riot.run_hours'))->isPast()) {
                $run->finish('failed', 'Tempo máximo atingido. Inicie uma nova sincronização.');

                return;
            }
            $delay = 0;
            try {
                $advance->execute($run);
                if (! $run->isActive()) {
                    return;
                }
                $run->retries = 0;
                $run->status = 'running';
            } catch (RiotException $exception) {
                if (! $exception->retryable || (! $exception->throttled && ++$run->retries >= config('riot.max_retries'))) {
                    $run->finish('failed', $exception->getMessage());

                    return;
                }
                $run->status = 'retrying';
                $run->message = $exception->getMessage();
                $delay = $exception->retryAfter;
            } catch (Throwable $exception) {
                // Do not log exception messages/payloads: they can contain credentials or private data.
                Log::error('Account synchronization failed.', ['run_id' => $run->id, 'type' => $exception::class]);
                $run->finish('failed', 'Não foi possível concluir a sincronização. Os dados já confirmados foram preservados.');

                return;
            }
            $run->version++;
            $run->available_at = now()->addSeconds($delay);
            $run->save();
            self::dispatch($run->id, $run->version)->onConnection(config('riot.queue_connection'))
                ->onQueue(config('riot.queue'))->delay(now()->addSeconds($delay));
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $run = SyncRun::find($this->runId);
        if ($run?->isActive() && $run->version === $this->version) {
            $run->finish('failed', 'O worker interrompeu a tarefa. Você pode iniciar uma nova sincronização.');
        }
    }
}
