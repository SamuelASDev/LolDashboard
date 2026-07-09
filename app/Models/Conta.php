<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conta extends Model
{
    protected $fillable = [
        'user_id',
        'nick',
        'riot_id',
        'email',
        'regiao',
        'elo',
        'pdl',
        'wins',
        'losses',
        'winrate',
        'cutoff_lp',
        'last_game_at' // ⬅️ Adicionado aqui
    ];

    protected $casts = [
    'last_game_at' => 'datetime',
    ];

    // 🔗 Uma conta pertence a um usuário
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}