<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RankCutoff extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['lp' => 'integer', 'queue_id' => 'integer', 'source_updated_at' => 'datetime',
            'fetched_at' => 'datetime', 'last_attempt_at' => 'datetime', 'last_failed_at' => 'datetime'];
    }

    public function isStale(): bool
    {
        return ! $this->fetched_at || ! $this->source_updated_at
            || $this->fetched_at->lt(now()->subHours(36))
            || $this->source_updated_at->lt(now()->subHours(36))
            || $this->last_failed_at !== null;
    }
}
