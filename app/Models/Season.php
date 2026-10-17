<?php

namespace App\Models;

use App\Enums\RiotRegion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    protected $fillable = ['name'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'regional_starts' => 'array'];
    }

    public static function current(): self
    {
        return static::where('active_slot', 1)->firstOrFail();
    }

    public function startFor(RiotRegion $region): CarbonImmutable
    {
        return CarbonImmutable::parse($this->regional_starts[$region->value] ?? $this->starts_at, 'UTC')->utc();
    }

    public function constrainMatches(Builder $query): Builder
    {
        return $query->where(function ($scope) {
            foreach (RiotRegion::cases() as $region) {
                $scope->orWhere(function ($q) use ($region) {
                    $q->where('contas.regiao', $region->value)
                        ->whereRaw('COALESCE(partidas.started_at, partidas.played_at) >= ?', [$this->startFor($region)->toDateTimeString()])
                        ->when($this->ends_at, fn ($q) => $q->whereRaw('COALESCE(partidas.started_at, partidas.played_at) < ?', [$this->ends_at->toDateTimeString()]));
                });
            }
        });
    }
}
