<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class CronWorkerTest extends TestCase
{
    public function test_check_bootstraps_without_consuming_jobs(): void
    {
        $process = new Process([PHP_BINARY, base_path('scripts/cron-worker.php'), '--check'], base_path(), [
            'APP_ENV' => 'testing', 'RIOT_QUEUE_CONNECTION' => 'database',
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
        ]);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString('Nenhum job executado', $process->getOutput());
    }

    public function test_overlapping_invocation_exits_before_starting_worker(): void
    {
        $lock = fopen(storage_path('framework/cron-worker.lock'), 'c');
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        try {
            $process = new Process([PHP_BINARY, base_path('scripts/cron-worker.php')], base_path());
            $process->run();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertStringContainsString('execução ignorada', $process->getOutput());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
