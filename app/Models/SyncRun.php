<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    protected $attributes = ['version' => 0, 'cursor' => 0, 'processed' => 0, 'imported' => 0,
        'queue_id' => 420, 'mode' => 'full', 'stop_after_page' => false, 'retries' => 0, 'status' => 'queued', 'phase' => 'snapshot'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'cursor' => 'integer', 'processed' => 'integer', 'imported' => 'integer', 'retries' => 'integer', 'mode' => 'string', 'queue_id' => 'integer', 'stop_after_page' => 'boolean', 'context' => 'array', 'pending_ids' => 'array', 'finished_at' => 'datetime', 'available_at' => 'datetime'];
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class);
    }

    public function isActive(): bool
    {
        return $this->active_conta_id !== null;
    }

    public function finish(string $status, string $message): void
    {
        $this->forceFill(['status' => $status, 'message' => $message,
            'finished_at' => now(), 'active_conta_id' => null])->save();
    }
}
