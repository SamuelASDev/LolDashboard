<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RankSnapshot extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['observed_at' => 'datetime', 'lp' => 'integer', 'wins' => 'integer', 'losses' => 'integer'];
    }

    public function getLabelAttribute(): string
    {
        return $this->tier === 'UNRANKED' ? 'Sem classificação' : $this->tier.' '.$this->division.' · '.$this->lp.' LP';
    }

    public function getChartValueAttribute(): ?int
    {
        $tiers = ['IRON' => 0, 'BRONZE' => 400, 'SILVER' => 800, 'GOLD' => 1200,
            'PLATINUM' => 1600, 'EMERALD' => 2000, 'DIAMOND' => 2400,
            'MASTER' => 2800, 'GRANDMASTER' => 2800, 'CHALLENGER' => 2800];
        if (! isset($tiers[$this->tier])) {
            return null;
        }
        $division = in_array($this->tier, ['MASTER', 'GRANDMASTER', 'CHALLENGER'], true)
            ? 0 : (['IV' => 0, 'III' => 100, 'II' => 200, 'I' => 300][$this->division] ?? 0);

        return $tiers[$this->tier] + $division + $this->lp;
    }
}
