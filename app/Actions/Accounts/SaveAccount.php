<?php

namespace App\Actions\Accounts;

use App\Models\Conta;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAccount
{
    public function execute(User $user, array $data, ?Conta $conta = null): Conta
    {
        try {
            return DB::transaction(function () use ($user, $data, $conta) {
                // Serialize identity checks per owner, including simultaneous registrations.
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if (! $conta && $user->contas()->count() >= config('workspace.max_accounts')) {
                    throw ValidationException::withMessages(['riot_id' => 'Limite de contas atingido.']);
                }
                if ($conta) {
                    $conta = $user->contas()->whereKey($conta->id)->lockForUpdate()->firstOrFail();
                    if ($conta->syncRuns()->whereNotNull('active_conta_id')->exists()) {
                        throw ValidationException::withMessages(['riot_id' => 'Aguarde a sincronização terminar antes de editar.']);
                    }
                    if ($conta->puuid && ($conta->riot_id !== $data['riot_id'] || $conta->regiao !== $data['regiao'])) {
                        throw ValidationException::withMessages(['riot_id' => 'Cadastre outra conta para mudar a identidade confirmada.']);
                    }
                }
                if ($user->contas()->when($conta, fn ($q) => $q->whereKeyNot($conta->id))
                    ->where('regiao', $data['regiao'])->get(['riot_id'])
                    ->contains(fn ($other) => mb_strtolower($other->riot_id) === mb_strtolower($data['riot_id']))) {
                    throw ValidationException::withMessages(['riot_id' => 'Esta conta já está cadastrada.']);
                }
                $conta ??= $user->contas()->make();
                $conta->fill($data);
                $conta->identity_key = Conta::identityKey($user->id, $data['regiao'], $data['riot_id']);
                $conta->save();

                return $conta;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['riot_id' => 'Esta conta já está cadastrada.']);
        }
    }
}
