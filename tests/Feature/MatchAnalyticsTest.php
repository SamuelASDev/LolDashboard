<?php

namespace Tests\Feature;

use App\Actions\Sync\AdvanceSync;
use App\Actions\Sync\ImportMatch;
use App\Enums\RiotRegion;
use App\Exceptions\RiotException;
use App\Models\MatchParticipant;
use App\Models\RiotMatch;
use App\Queries\AccountInsights;
use App\Queries\AccountStatistics;
use App\Services\RiotService;
use App\Support\HistoryWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class MatchAnalyticsTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    private function completePayload(): array
    {
        $players = [];
        foreach (range(1, 10) as $slot) {
            $players[] = ['participantId' => $slot, 'puuid' => 'player-'.$slot, 'teamId' => $slot <= 5 ? 100 : 200,
                'championId' => $slot === 1 ? 103 : 99, 'championName' => $slot === 1 ? 'Ahri' : 'Lux',
                'riotIdGameName' => $slot === 2 ? '<script>alert(1)</script>' : 'Jogador '.$slot, 'riotIdTagline' => 'BR1',
                'individualPosition' => ['TOP', 'JUNGLE', 'MIDDLE', 'BOTTOM', 'UTILITY'][($slot - 1) % 5],
                'teamPosition' => ['TOP', 'JUNGLE', 'MIDDLE', 'BOTTOM', 'UTILITY'][($slot - 1) % 5],
                'win' => $slot <= 5, 'kills' => 5, 'deaths' => 2, 'assists' => 7, 'champLevel' => 18,
                'totalMinionsKilled' => 170, 'neutralMinionsKilled' => 10, 'goldEarned' => 12000,
                'totalDamageDealtToChampions' => 24000, 'totalDamageTaken' => 18000,
                'visionScore' => 25, 'wardsPlaced' => 12, 'wardsKilled' => 3, 'visionWardsBoughtInGame' => 2,
                'pentaKills' => 0, 'totalHeal' => 1000, 'item0' => 3089, 'summoner1Id' => 4, 'summoner2Id' => 14,
                'perks' => ['styles' => [['selections' => [['perk' => 8112]]]]]];
        }

        return $this->matchPayload(info: ['participants' => $players, 'gameVersion' => '16.20.1',
            'teams' => [['teamId' => 100, 'objectives' => ['dragon' => ['kills' => 3]], 'bans' => [['championId' => 266]]]]]);
    }

    public function test_details_import_all_players_and_calculate_personal_metrics_idempotently(): void
    {
        $account = $this->account(attributes: ['puuid' => 'player-1']);
        $importer = app(ImportMatch::class);
        $this->assertTrue($importer->execute($account, 'BR1_DETAIL', $this->completePayload()));
        $this->assertFalse($importer->execute($account, 'BR1_DETAIL', $this->completePayload()));
        $this->assertDatabaseCount('riot_matches', 1);
        $this->assertDatabaseCount('match_participants', 10);
        $this->assertDatabaseCount('partidas', 1);
        $match = $account->partidas()->first();
        $this->assertSame(180, $match->cs);
        $this->assertSame(6.0, $match->cs_per_minute);
        $this->assertSame(48.0, $match->kill_participation);
        $this->assertSame('Lux', $match->opponent_champion_name);
        $this->assertNotNull($match->details_synced_at);
        $this->assertArrayNotHasKey('payload', RiotMatch::first()->toArray());
    }

    public function test_every_tab_renders_and_scoreboard_escapes_external_names_without_network(): void
    {
        Http::fake();
        $account = $this->account(attributes: ['puuid' => 'player-1']);
        app(ImportMatch::class)->execute($account, 'BR1_DETAIL', $this->completePayload());
        $this->actingAs($account->user);
        foreach (['overview', 'history', 'champions', 'matchups', 'patterns', 'records', 'progress'] as $tab) {
            $response = $this->get('/contas/'.$account->id.'?tab='.$tab)->assertOk();
            if ($tab === 'history') {
                $response->assertSee('Jogador 10#BR1')->assertSee('48')->assertSee('3089.png', false)
                    ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;#BR1', false)
                    ->assertDontSee('<script>alert(1)</script>', false);
            }
        }
        Http::assertNothingSent();
    }

    public function test_cached_match_is_reused_for_another_account_without_api_request(): void
    {
        Http::fake();
        $account = $this->account(attributes: ['puuid' => 'player-1']);
        app(ImportMatch::class)->execute($account, 'BR1_DETAIL', $this->completePayload());
        $other = $this->account(attributes: ['puuid' => 'player-6']);
        $run = $other->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)], 'active_conta_id' => $other->id, 'phase' => 'history', 'pending_ids' => ['BR1_DETAIL']]);
        app(AdvanceSync::class)->execute($run);
        Http::assertNothingSent();
        $this->assertDatabaseCount('riot_matches', 1);
        $this->assertDatabaseCount('partidas', 2);
        $this->assertFalse($other->partidas()->first()->win);
    }

    public function test_filters_and_owner_boundary_apply_to_details_and_analytics(): void
    {
        $account = $this->account(attributes: ['puuid' => 'player-1']);
        app(ImportMatch::class)->execute($account, 'BR1_DETAIL', $this->completePayload());
        $this->actingAs($account->user)->get('/contas/'.$account->id.'?tab=history&champion=Zed')
            ->assertOk()->assertDontSee('Jogador 10#BR1');
        $this->get('/contas/'.$account->id.'?tab=history&result=loss')->assertOk()->assertDontSee('Jogador 10#BR1');
        $this->get('/contas/'.$account->id.'?tab=history&queue=440')->assertSessionHasErrors('queue');
        $other = $this->account();
        $this->actingAs($other->user)->get('/contas/'.$account->id.'?tab=history')->assertNotFound();
        $this->actingAs($account->user)->get('/contas/'.$account->id.'?queue=999')->assertSessionHasErrors('queue');
    }

    public function test_recent_sync_stops_at_a_fully_detailed_page_and_enriches_old_rows(): void
    {
        $account = $this->account(attributes: ['puuid' => 'player-1']);
        $this->matchRecord($account, ['match_id' => 'BR1_DETAIL']);
        Http::fake(['*/ids*' => Http::response(['BR1_DETAIL']), '*/matches/BR1_DETAIL' => Http::response($this->completePayload())]);
        $run = $account->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)], 'active_conta_id' => $account->id, 'phase' => 'history', 'mode' => 'recent']);
        $advance = app(AdvanceSync::class);
        $advance->execute($run);
        $this->assertSame(['BR1_DETAIL'], $run->pending_ids);
        $advance->execute($run);
        $this->assertNotNull($account->partidas()->first()->details_synced_at);
        $advance->execute($run);
        $this->assertTrue($run->stop_after_page);
        $advance->execute($run);
        $this->assertSame('completed', $run->status);
        Http::assertSentCount(3);
    }

    public function test_patterns_use_only_confirmed_rows_and_keep_missing_metrics_absent(): void
    {
        $account = $this->account(attributes: ['puuid' => 'p']);
        foreach ([true, true, false, false, false] as $index => $win) {
            $this->matchRecord($account, ['win' => $win, 'played_at' => now()->subHours(5 - $index)]);
        }
        $this->matchRecord($account, ['identity_puuid' => null, 'kills' => 999]);
        $base = app(AccountStatistics::class)->base($account->user, 30, $account);
        $insights = app(AccountInsights::class)->build($base);
        $this->assertSame(5, $insights['total']);
        $this->assertSame(2, $insights['bestWins']);
        $this->assertSame(3, $insights['bestLosses']);
        $this->assertSame(3, $insights['streak']);
        $this->assertSame([], $insights['averages']);
        $this->assertSame(5, $insights['records']['kills']['value']);
    }

    public function test_invalid_participant_does_not_partially_write_shared_details(): void
    {
        $account = $this->account(attributes: ['puuid' => 'player-1']);
        $payload = $this->completePayload();
        $payload['info']['participants'][9]['kills'] = -5;
        try {
            app(ImportMatch::class)->execute($account, 'BR1_INVALID', $payload);
            $this->fail('Invalid participant accepted.');
        } catch (RiotException) {
            $this->assertSame(0, RiotMatch::count());
            $this->assertSame(0, MatchParticipant::count());
            $this->assertSame(0, $account->partidas()->count());
        }
    }

    public function test_server_rate_headers_pause_other_runs_without_spending_more_requests(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response(['puuid' => 'p'], 200, [
            'X-App-Rate-Limit' => '1:120', 'X-App-Rate-Limit-Count' => '1:120',
        ])]);
        $service = app(RiotService::class);
        $service->account('Test#BR1', RiotRegion::BR);
        try {
            $service->account('Other#BR1', RiotRegion::BR);
            $this->fail('Server limit ignored.');
        } catch (RiotException $exception) {
            $this->assertTrue($exception->throttled);
            $this->assertGreaterThanOrEqual(120, $exception->retryAfter);
            Http::assertSentCount(1);
        } finally {
            Cache::flush();
        }
    }

    public function test_recent_window_finishes_without_more_network_and_client_rejects_flex(): void
    {
        $account = $this->account(attributes: ['puuid' => 'p']);
        Http::fake(['*/ids*' => Http::response([])]);
        $run = $account->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)], 'active_conta_id' => $account->id, 'phase' => 'history',
            'mode' => 'recent', 'cursor' => 60]);
        app(AdvanceSync::class)->execute($run);
        $this->assertSame('completed', $run->status);
        Http::assertNothingSent();
        try {
            app(RiotService::class)->matchIds('p', RiotRegion::BR, 0, 440);
            $this->fail('Fila desativada foi enviada para a Riot.');
        } catch (RiotException) {
            Http::assertNothingSent();
        }
    }

    public function test_flex_is_rejected_without_changing_existing_history(): void
    {
        $account = $this->account(attributes: ['puuid' => 'puuid-1']);
        $legacy = $this->matchRecord($account, ['queue_id' => 440]);
        $payload = $this->completePayload();
        $payload['info']['queueId'] = 440;
        $this->assertFalse(app(ImportMatch::class)->execute($account, 'BR1_FLEX', $payload));
        $this->assertDatabaseHas('partidas', ['id' => $legacy->id, 'queue_id' => 440]);
        $this->actingAs($account->user)->get('/contas/'.$account->id.'?queue=440')->assertSessionHasErrors('queue');
        $this->post('/contas/'.$account->id.'/sync', ['mode' => 'full', 'queue' => 440])->assertSessionHasErrors('queue');
        Http::assertNothingSent();
    }

    public function test_ambiguous_enemy_position_is_not_assigned_an_arbitrary_opponent(): void
    {
        $account = $this->account(attributes: ['puuid' => 'player-1']);
        $payload = $this->completePayload();
        $payload['info']['participants'][6]['teamPosition'] = 'TOP';
        $payload['info']['participants'][6]['individualPosition'] = 'TOP';
        app(ImportMatch::class)->execute($account, 'BR1_AMBIGUOUS', $payload);
        $match = $account->partidas()->first();
        $this->assertSame('TOP', $match->lane);
        $this->assertNull($match->opponent_champion_name);
        $this->assertNull($match->opponent_champion_id);
    }
}
