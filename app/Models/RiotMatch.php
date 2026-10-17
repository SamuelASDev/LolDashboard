<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiotMatch extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'teams' => 'array', 'started_at' => 'datetime',
            'played_at' => 'datetime', 'duration_seconds' => 'integer'];
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(Partida::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MatchParticipant::class)->orderBy('team_id')->orderBy('participant_id');
    }

    public function scopeForDisplay($query)
    {
        return $query->select(['id', 'match_id', 'routing', 'queue_id', 'duration_seconds',
            'started_at', 'played_at', 'game_version', 'teams']);
    }
}
