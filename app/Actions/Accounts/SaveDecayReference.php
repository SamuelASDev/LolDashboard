<?php

namespace App\Actions\Accounts;

use App\Models\Season;
use App\Models\User;
use App\Queries\AccountDecay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveDecayReference
{
    public function execute(User $user, int $id, ?int $days): void
    {
        DB::transaction(function () use ($user, $id, $days) {
            $account = $user->contas()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($days !== null && (! AccountDecay::applies($account) || $days < 0 || $days > 14)) {
                throw ValidationException::withMessages(['decay_days' => 'Informe de 0 a 14 dias para uma conta Mestre+ confirmada.']);
            }
            $account->forceFill(['decay_reference' => $days === null ? null : [
                'days' => $days, 'observed_at' => now()->utc()->startOfSecond()->toIso8601String(),
                'puuid' => $account->puuid, 'region' => $account->regiao, 'season_id' => Season::current()->id,
            ]])->save();
        });
    }
}
