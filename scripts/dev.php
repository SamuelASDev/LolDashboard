<?php

// Cross-platform development runner: no shell-composed user input or PCNTL dependency.
require __DIR__.'/../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$npm = PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm';
$commands = [
    'web' => [PHP_BINARY, 'artisan', 'serve'],
    'riot' => [PHP_BINARY, 'artisan', 'queue:work', config('riot.queue_connection'), '--queue='.config('riot.queue'), '--sleep=1', '--tries=3', '--timeout=90'],
    'scheduler' => [PHP_BINARY, 'artisan', 'schedule:work'],
    'vite' => [$npm, 'run', 'dev'],
];
$processes = [];
$exitCode = 0;
try {
    foreach ($commands as $name => $command) {
        $process = new Process($command, $root, timeout: null);
        $process->start();
        $processes[$name] = $process;
    }
    while (true) {
        foreach ($processes as $name => $process) {
            foreach ([$process->getIncrementalOutput(), $process->getIncrementalErrorOutput()] as $output) {
                if ($output !== '') {
                    fwrite(STDOUT, '['.$name.'] '.$output);
                }
            }
            if (! $process->isRunning()) {
                throw new RuntimeException($name.' encerrou. Consulte a saída acima.');
            }
        }
        usleep(100000);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    $exitCode = 1;
} finally {
    foreach ($processes as $process) {
        $process->stop(2);
    }
}

exit($exitCode);
