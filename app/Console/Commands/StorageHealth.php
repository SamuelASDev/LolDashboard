<?php

namespace App\Console\Commands;

use App\Models\Partida;
use App\Models\RiotMatch;
use Illuminate\Console\Command;

class StorageHealth extends Command
{
    protected $signature = 'dashboard:storage-health';

    protected $description = 'Relata volume e cache sem referência, sem excluir dados';

    public function handle(): int
    {
        $cutoff = now()->subDays(config('workspace.orphan_retention_days'));
        $orphan = RiotMatch::whereDoesntHave('partidas')->where('updated_at', '<', $cutoff);
        $bytes = 0;
        foreach (RiotMatch::select(['id', 'payload'])->cursor() as $game) {
            $bytes += strlen(json_encode($game->payload, JSON_THROW_ON_ERROR));
        }
        $this->table(['Indicador', 'Valor'], [
            ['Partidas pessoais preservadas', Partida::count()],
            ['Caches Riot', RiotMatch::count()],
            ['Payloads aproximados (bytes JSON)', $bytes],
            ['Caches sem referência anteriores a '.$cutoff->toDateString(), (clone $orphan)->count()],
            ['Limpeza automática', 'Desativada; revisão e backup obrigatórios'],
        ]);

        return self::SUCCESS;
    }
}
