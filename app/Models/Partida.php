<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partida extends Model
{
    protected $fillable = [
        'conta_id', 'match_id', 'champion_name', 'win', 'kills', 'deaths', 'assists', 'lane', 'played_at'
    ];

    protected $casts = [
        'played_at' => 'datetime',
        'win' => 'boolean'
    ];

    // Uma partida pertence a uma conta de invocador
    public function conta()
    {
        return $this->belongsTo(Conta::class);
    }
}