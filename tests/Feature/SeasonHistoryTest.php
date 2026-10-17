<?php

namespace Tests\Feature;

use App\Actions\Sync\AdvanceSync;
use App\Actions\Sync\ImportMatch;
use App\Actions\Sync\StartSync;
use App\Enums\RiotRegion;
use App\Services\RiotService;
use App\Support\HistoryWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class SeasonHistoryTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    public static function platforms(): array
    {
        return [['br1', '15:00'], ['na1', '20:00'], ['euw1', '12:00'],
            ['eun1', '11:00'], ['kr', '03:00'], ['jp1', '03:00']];
    }

    #[DataProvider('platforms')]
    public function test_api_requests_are_bounded_by_the_actual_season_start_in_server_time(string $platform, string $utcTime): void
    {
        Http::fake(['*/ids*' => Http::response([])]);
        app(RiotService::class)->matchIds('p', RiotRegion::from($platform), 0);
        $expected = CarbonImmutable::parse('2026-01-08 '.$utcTime, 'UTC')->getTimestamp();
        Http::assertSent(fn ($request) => $request['startTime'] === $expected
            && is_int($request['endTime']) && $request['endTime'] <= now()->getTimestamp()
            && $request['endTime'] > $request['startTime']);
    }

    public function test_window_is_saved_before_dispatch_and_remains_fixed_when_the_run_advances(): void
    {
        Queue::fake();
        $account = $this->account(attributes: ['puuid' => 'p']);
        $run = app(StartSync::class)->execute($account, 'full');
        $window = $run->context['history_window'];
        $this->assertSame('2026', $window['season']);
        $run->phase = 'history';
        $run->save();
        $this->travel(1)->hours();
        Http::fake(['*/ids*' => Http::response([])]);
        app(AdvanceSync::class)->execute($run);
        Http::assertSent(fn ($request) => $request['startTime'] === $window['start'] && $request['endTime'] === $window['end']);
        $this->assertSame('completed', $run->status);
    }

    public function test_preseason_match_is_not_imported_even_when_it_ends_after_the_season_starts(): void
    {
        $account = $this->account(attributes: ['puuid' => 'p']);
        $window = HistoryWindow::forRegion(RiotRegion::BR);
        $payload = $this->matchPayload('p', info: ['gameStartTimestamp' => ($window['start'] - 60) * 1000,
            'gameEndTimestamp' => ($window['start'] + 1740) * 1000]);
        $this->assertFalse(app(ImportMatch::class)->execute($account, 'BR1_PRESEASON', $payload, $window));
        $this->assertDatabaseCount('partidas', 0);
        $this->assertDatabaseCount('riot_matches', 0);
        $payload['info']['gameStartTimestamp'] = $window['start'] * 1000;
        $this->assertTrue(app(ImportMatch::class)->execute($account, 'BR1_SEASON', $payload, $window));
    }

    public function test_runs_from_the_old_unbounded_protocol_stop_without_more_http(): void
    {
        Http::fake();
        $account = $this->account(attributes: ['puuid' => 'p']);
        $run = $account->syncRuns()->create(['active_conta_id' => $account->id, 'phase' => 'history',
            'cursor' => 2000, 'pending_ids' => ['BR1_OLD']]);
        app(AdvanceSync::class)->execute($run);
        $this->assertSame('cancelled', $run->status);
        $this->assertFalse($run->isActive());
        Http::assertNothingSent();
    }

    public function test_repeated_full_history_page_is_stopped_instead_of_scanning_forever(): void
    {
        $account = $this->account(attributes: ['puuid' => 'p']);
        $run = $account->syncRuns()->create(['active_conta_id' => $account->id, 'phase' => 'history',
            'mode' => 'full', 'context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)]]);
        Http::fake(['*/ids*' => Http::response(['BR1_REPEAT']),
            '*/matches/BR1_REPEAT' => Http::response($this->matchPayload('p'))]);
        $advance = app(AdvanceSync::class);
        $advance->execute($run);
        $advance->execute($run);
        $advance->execute($run);
        $this->assertSame('partial', $run->status);
        $this->assertFalse($run->isActive());
        $this->assertDatabaseCount('partidas', 1);
        Http::assertSentCount(3);
    }

    public function test_out_of_window_api_response_stops_before_importing_or_fetching_more_details(): void
    {
        $account = $this->account(attributes: ['puuid' => 'p']);
        $window = HistoryWindow::forRegion(RiotRegion::BR);
        $run = $account->syncRuns()->create(['active_conta_id' => $account->id, 'phase' => 'history',
            'pending_ids' => ['BR1_OLD', 'BR1_OLDER'], 'context' => ['history_window' => $window]]);
        Http::fake(['*/matches/BR1_OLD' => Http::response($this->matchPayload('p', info: [
            'gameStartTimestamp' => ($window['start'] - 3600) * 1000,
            'gameEndTimestamp' => ($window['start'] - 1800) * 1000,
        ]))]);
        app(AdvanceSync::class)->execute($run);
        $this->assertSame('partial', $run->status);
        $this->assertDatabaseCount('partidas', 0);
        Http::assertSentCount(1);
    }

    public function test_historical_records_already_in_the_database_are_preserved(): void
    {
        $account = $this->account(attributes: ['puuid' => 'p']);
        $this->matchRecord($account, ['played_at' => CarbonImmutable::parse('2025-12-01', 'UTC')]);
        $run = $account->syncRuns()->create(['active_conta_id' => $account->id, 'phase' => 'history']);
        Http::fake(['*/ids*' => Http::response([])]);
        app(AdvanceSync::class)->execute($run);
        $this->assertDatabaseCount('partidas', 1);
        $this->assertSame('completed', $run->status);
    }
}
