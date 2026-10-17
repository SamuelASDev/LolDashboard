<?php

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Output\ConsoleOutput;

// Este arquivo deve ser chamado pelo PHP CLI, nunca por uma URL pública.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
chdir($root);
$lock = fopen($root.'/storage/framework/cron-worker.lock', 'c');
if ($lock === false) {
    fwrite(STDERR, "Não foi possível abrir o lock. Confira as permissões de storage/framework.\n");
    exit(1);
}
if (! flock($lock, LOCK_EX | LOCK_NB)) {
    fclose($lock);
    fwrite(STDOUT, "Worker anterior ainda ativo; execução ignorada.\n");
    exit(0);
}

$status = 0;
try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $connection = config('riot.queue_connection');
    if (! in_array(config('queue.connections.'.$connection.'.driver'), ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
        throw new RuntimeException('Conexão de fila incompatível.');
    }
    if (in_array('--check', $argv, true)) {
        fwrite(STDOUT, "Bootstrap, configuração da fila e lock PHP: OK. Nenhum job executado.\n");
    } else {
        $status = $kernel->call('queue:work', [
            'connection' => $connection, '--queue' => config('riot.queue'),
            '--stop-when-empty' => true, '--max-time' => 50,
            '--sleep' => 1, '--tries' => 3, '--timeout' => 90,
        ], new ConsoleOutput);
    }
} catch (Throwable $exception) {
    // Não expor mensagem/trace: podem conter configuração e credenciais.
    fwrite(STDERR, 'Falha no cron worker ('.$exception::class."). Confira configuração, dependências e permissões.\n");
    $status = 1;
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

exit($status);
