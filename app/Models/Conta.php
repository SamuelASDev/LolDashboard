<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conta extends Model
{
    protected $fillable = ['nick', 'riot_id', 'regiao'];

    protected function casts(): array
    {
        return ['decay_reference' => 'array', 'last_game_at' => 'datetime', 'rank_synced_at' => 'datetime',
            'history_synced_at' => 'datetime', 'solo_history_synced_at' => 'datetime', 'solo_history_window' => 'array', 'pdl' => 'integer', 'wins' => 'integer',
            'losses' => 'integer', 'winrate' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(Partida::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    public function latestSync(): HasOne
    {
        return $this->hasOne(SyncRun::class)->latestOfMany();
    }

    public static function identityKey(int $userId, string $region, string $riotId): string
    {
        return hash('sha256', $userId.'|'.$region.'|'.mb_strtolower(trim($riotId)));
    }

    public function rankSnapshots(): HasMany
    {
        return $this->hasMany(RankSnapshot::class);
    }
}
