<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PromoteAdmin extends Command
{
    protected $signature = 'app:promote-admin {email : E-mail de um usuário já cadastrado}';

    protected $description = 'Concede acesso administrativo a um usuário existente pelo console seguro do servidor.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Usuário não encontrado.');

            return self::FAILURE;
        }
        if ($user->role === 'admin') {
            $this->info('Este usuário já é administrador.');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $old = $user->role;
            $user->forceFill(['role' => 'admin'])->save();
            AuditEvent::create(['subject_id' => $user->id, 'action' => 'user.promoted_by_console',
                'metadata' => ['from' => $old, 'to' => 'admin']]);
        });
        $this->info('Acesso administrativo concedido. A verificação de e-mail continua obrigatória.');

        return self::SUCCESS;
    }
}
