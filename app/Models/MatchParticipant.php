<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchParticipant extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['items' => 'array', 'spells' => 'array', 'runes' => 'array', 'stats' => 'array',
            'win' => 'boolean', 'team_id' => 'integer', 'champion_id' => 'integer'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(RiotMatch::class, 'riot_match_id');
    }
}
