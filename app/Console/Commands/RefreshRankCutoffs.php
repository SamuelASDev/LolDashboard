<?php

namespace App\Console\Commands;

use App\Actions\Sync\RefreshRankCutoffs as Refresh;
use Illuminate\Console\Command;

class RefreshRankCutoffs extends Command
{
    protected $signature = 'riot:cutoffs {--force : Atualiza mesmo após a consulta do dia}';

    protected $description = 'Atualiza os cortes públicos Solo/Duo, compartilhados entre todas as contas.';

    public function handle(Refresh $refresh): int
    {
        $status = $refresh->execute((bool) $this->option('force'));
        $this->line(match ($status) {
            'updated' => 'Cortes atualizados.',
            'cached' => 'Consulta diária já realizada; valores preservados.',
            'busy' => 'Outra atualização está em andamento.',
            'partial' => 'Algumas regiões não puderam ser atualizadas; últimos valores preservados.',
            default => 'Não foi possível atualizar os cortes; últimos valores preservados.',
        });

        return in_array($status, ['failed', 'partial'], true) ? self::FAILURE : self::SUCCESS;
    }
}
