<?php

namespace App\Actions\Sync;

use App\Enums\RiotRegion;
use App\Models\RankCutoff;
use App\Services\ReplaysCutoffSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JsonException;
use UnexpectedValueException;

class RefreshRankCutoffs
{
    public function __construct(private ReplaysCutoffSource $source) {}

    /** The attempt is persisted before HTTP so failures/restarts cannot create a request loop. */
    public function execute(bool $force = false): string
    {
        $lock = Cache::lock('rank-cutoffs:refresh', 120);
        if (! $lock->get()) {
            return 'busy';
        }
        try {
            if (! $force && RankCutoff::where('last_attempt_at', '>=', now()->timezone('America/Sao_Paulo')->startOfDay()->utc())->exists()) {
                return 'cached';
            }
            DB::transaction(function () {
                foreach (RiotRegion::cases() as $region) {
                    foreach (['GRANDMASTER', 'CHALLENGER'] as $tier) {
                        RankCutoff::updateOrCreate(['region' => $region->value, 'queue_id' => 420, 'tier' => $tier],
                            ['last_attempt_at' => now(), 'last_failed_at' => now()]);
                    }
                }
            });
            try {
                $catalog = $this->source->fetch();
            } catch (ConnectionException|JsonException|UnexpectedValueException) {
                return 'failed';
            }
            $status = 'updated';
            foreach (RiotRegion::cases() as $region) {
                try {
                    $values = $this->source->platform($catalog, $region->value);
                } catch (UnexpectedValueException) {
                    $status = 'partial';

                    continue;
                }
                DB::transaction(function () use ($region, $values) {
                    foreach (['GRANDMASTER', 'CHALLENGER'] as $tier) {
                        RankCutoff::where('region', $region->value)->where('queue_id', 420)->where('tier', $tier)->update([
                            'lp' => $values[$tier], 'source_url' => ReplaysCutoffSource::URL,
                            'source_updated_at' => $values['updated_at'], 'fetched_at' => now(), 'last_failed_at' => null,
                            'updated_at' => now(),
                        ]);
                    }
                });
            }

            return $status;
        } finally {
            $lock->release();
        }
    }
}
