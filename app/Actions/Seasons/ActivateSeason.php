<?php

namespace App\Actions\Seasons;

use App\Models\AuditEvent;
use App\Models\Season;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateSeason
{
    public function execute(Season $season, ?User $actor = null): void
    {
        DB::transaction(function () use ($season, $actor) {
            // Lock the complete catalog in a stable order: activation is a single atomic switch.
            $catalog = Season::orderBy('id')->lockForUpdate()->get();
            $target = $catalog->firstWhere('id', $season->id);
            if (! $target || $target->status !== 'draft') {
                throw ValidationException::withMessages(['season' => 'Somente uma temporada ainda não iniciada pode ser ativada.']);
            }
            $at = now()->startOfSecond();
            $current = $catalog->firstWhere('active_slot', 1);
            if ($current && $at->lessThanOrEqualTo($current->starts_at)) {
                throw ValidationException::withMessages(['season' => 'A troca precisa ocorrer depois do início da temporada atual.']);
            }
            $current?->forceFill(['status' => 'ended', 'active_slot' => null, 'ends_at' => $at])->save();
            $target->forceFill(['status' => 'active', 'active_slot' => 1, 'starts_at' => $at])->save();
            AuditEvent::create([
                'actor_id' => $actor?->id, 'action' => 'season.activated',
                'metadata' => ['season_id' => $target->id, 'previous_season_id' => $current?->id, 'switched_at' => $at->toIso8601String()],
            ]);
        });
    }
}
