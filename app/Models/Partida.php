<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partida extends Model
{
    protected $fillable = ['conta_id', 'match_id', 'champion_name', 'win', 'kills', 'deaths',
        'assists', 'lane', 'started_at', 'played_at', 'identity_puuid', 'queue_id', 'is_remake', 'riot_match_id', 'champion_id', 'team_id', 'duration_seconds', 'cs', 'gold', 'damage', 'damage_taken', 'vision_score', 'wards_placed', 'wards_killed', 'control_wards', 'kill_participation', 'opponent_champion_id', 'opponent_champion_name', 'ended_in_surrender', 'penta_kills', 'healing', 'items', 'spells', 'runes', 'details_synced_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'played_at' => 'datetime', 'win' => 'boolean', 'is_remake' => 'boolean',
            'kills' => 'integer', 'deaths' => 'integer', 'assists' => 'integer', 'items' => 'array', 'spells' => 'array', 'runes' => 'array', 'details_synced_at' => 'datetime', 'ended_in_surrender' => 'boolean', 'kill_participation' => 'float'];
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(RiotMatch::class, 'riot_match_id');
    }

    public function getCsPerMinuteAttribute(): ?float
    {
        return $this->cs !== null && $this->duration_seconds > 0 ? round($this->cs * 60 / $this->duration_seconds, 1) : null;
    }
}
