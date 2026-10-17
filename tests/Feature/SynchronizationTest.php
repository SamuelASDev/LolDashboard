<?php

namespace Tests\Feature;

use App\Actions\Sync\AdvanceSync;
use App\Actions\Sync\ImportMatch;
use App\Enums\RiotRegion;
use App\Exceptions\RiotException;
use App\Jobs\SyncAccount;
use App\Models\SyncRun;
use App\Support\HistoryWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class SynchronizationTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    private function runStep(SyncRun $run): void
    {
        $run->refresh();
        (new SyncAccount($run->id, $run->version))->handle(app(AdvanceSync::class));
        $run->refresh();
    }

    public function test_full_sync_imports_new_games_even_when_old_count_exceeds_current_rank_total(): void
    {
        Queue::fake();
        $conta = $this->account(attributes: ['puuid' => 'puuid-1', 'wins' => 999, 'elo' => 'MASTER I']);
        for ($i = 0; $i < 5; $i++) {
            $this->matchRecord($conta, ['played_at' => now()->subYear()]);
        }
        Http::fake([
            '*/riot/account/*' => Http::response(['puuid' => 'puuid-1', 'gameName' => 'Renomeado', 'tagLine' => 'BR1']),
            '*/lol/league/*' => Http::response([['queueType' => 'RANKED_SOLO_5x5', 'tier' => 'GOLD', 'rank' => 'I', 'leaguePoints' => 10, 'wins' => 1, 'losses' => 0]]),
            '*/ids*' => Http::sequence()->push(['BR1_NEW'])->push([]),
            '*/matches/BR1_NEW' => Http::response($this->matchPayload()),
        ]);
        $run = $conta->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)], 'active_conta_id' => $conta->id]);
        for ($i = 0; $i < 10 && $run->isActive(); $i++) {
            $this->runStep($run);
        }
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->imported);
        $this->assertSame('Renomeado#BR1', $conta->fresh()->riot_id);
        $this->assertSame(1, $conta->fresh()->wins);
        $this->assertDatabaseCount('partidas', 6);
        $this->assertNotNull($conta->fresh()->history_synced_at);
    }

    public function test_snapshot_failure_preserves_the_previous_rank(): void
    {
        Queue::fake();
        $conta = $this->account(attributes: ['puuid' => 'puuid-1', 'elo' => 'MASTER I', 'wins' => 25, 'pdl' => 350]);
        Http::fake(['*/riot/account/*' => Http::response(['puuid' => 'puuid-1']), '*/lol/league/*' => Http::response([], 403)]);
        $run = $conta->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)], 'active_conta_id' => $conta->id]);
        $this->runStep($run);
        $this->assertSame('failed', $run->status);
        $this->assertSame('MASTER I', $conta->fresh()->elo);
        $this->assertSame(350, $conta->fresh()->pdl);
        $this->assertNull($run->active_conta_id);
    }

    public function test_valid_unranked_response_resets_all_rank_fields_without_losing_matches(): void
    {
        Queue::fake();
        $conta = $this->account(attributes: ['puuid' => 'puuid-1', 'elo' => 'MASTER I', 'wins' => 25, 'pdl' => 350, 'losses' => 5, 'winrate' => 83.3, 'cutoff_lp' => 600]);
        $this->matchRecord($conta);
        Http::fake(['*/riot/account/*' => Http::response(['puuid' => 'puuid-1']), '*/lol/league/*' => Http::response([])]);
        $run = $conta->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)], 'active_conta_id' => $conta->id]);
        $this->runStep($run);
        $conta->refresh();
        $this->assertSame('UNRANKED', $conta->elo);
        $this->assertSame(0, $conta->pdl);
        $this->assertSame(0, $conta->wins);
        $this->assertSame(0.0, $conta->winrate);
        $this->assertNull($conta->cutoff_lp);
        $this->assertDatabaseCount('partidas', 1);
    }

    public function test_transient_failures_have_a_finite_retry_budget_and_preserve_the_cursor(): void
    {
        Queue::fake();
        $conta = $this->account(attributes: ['puuid' => 'puuid-1']);
        Http::fake(['*' => Http::response([], 500)]);
        $run = $conta->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)], 'active_conta_id' => $conta->id, 'phase' => 'history', 'cursor' => 20, 'pending_ids' => ['BR1_1']]);
        for ($i = 0; $i < 5; $i++) {
            $this->runStep($run);
        }
        $this->assertSame('failed', $run->status);
        $this->assertSame(20, $run->cursor);
        $this->assertSame(['BR1_1'], $run->pending_ids);
        Http::assertSentCount(5);
    }

    public function test_duplicate_job_versions_and_cancelled_runs_do_not_repeat_work(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response([])]);
        $conta = $this->account(attributes: ['puuid' => 'puuid-1']);
        $run = $conta->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)], 'active_conta_id' => $conta->id, 'phase' => 'history']);
        (new SyncAccount($run->id, 99))->handle(app(AdvanceSync::class));
        Http::assertNothingSent();
        $run->finish('cancelled', 'Cancelada');
        (new SyncAccount($run->id, 0))->handle(app(AdvanceSync::class));
        Http::assertNothingSent();
    }

    public function test_legacy_rows_are_confirmed_only_by_matching_participant_and_not_duplicated(): void
    {
        $conta = $this->account(attributes: ['puuid' => 'puuid-1']);
        $this->matchRecord($conta, ['match_id' => 'BR1_1', 'identity_puuid' => null, 'champion_name' => 'Wrong']);
        $import = app(ImportMatch::class);
        $this->assertFalse($import->execute($conta, 'BR1_1', $this->matchPayload()));
        $this->assertDatabaseHas('partidas', ['match_id' => 'BR1_1', 'identity_puuid' => 'puuid-1', 'champion_name' => 'Ahri']);
        $this->assertDatabaseCount('partidas', 1);
        $other = $this->account(attributes: ['puuid' => 'other']);
        $import->execute($other, 'BR1_1', $this->matchPayload('other'));
        $this->assertDatabaseCount('partidas', 2);
    }

    public function test_long_early_surrenders_are_kept_and_only_short_remakes_are_flagged(): void
    {
        $conta = $this->account(attributes: ['puuid' => 'puuid-1']);
        $import = app(ImportMatch::class);
        $import->execute($conta, 'BR1_LONG', $this->matchPayload(player: ['gameEndedInEarlySurrender' => true], info: ['gameDuration' => 900]));
        $import->execute($conta, 'BR1_SHORT', $this->matchPayload(player: ['gameEndedInEarlySurrender' => true], info: ['gameDuration' => 180]));
        $this->assertDatabaseHas('partidas', ['match_id' => 'BR1_LONG', 'is_remake' => false]);
        $this->assertDatabaseHas('partidas', ['match_id' => 'BR1_SHORT', 'is_remake' => true]);
    }

    public function test_recovery_reschedules_a_stale_run_without_resetting_history(): void
    {
        Queue::fake();
        $conta = $this->account(attributes: ['puuid' => 'p']);
        $run = $conta->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)], 'active_conta_id' => $conta->id, 'phase' => 'history', 'cursor' => 40, 'pending_ids' => ['BR1_1'], 'updated_at' => now()->subMinutes(10)]);
        $this->artisan('riot:recover')->assertSuccessful();
        Queue::assertPushed(SyncAccount::class, fn ($job) => $job->runId === $run->id);
        $this->assertSame(40, $run->fresh()->cursor);
    }

    public function test_per_account_lock_prevents_recovery_while_a_step_is_running(): void
    {
        Queue::fake();
        $conta = $this->account();
        $run = $conta->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)], 'active_conta_id' => $conta->id, 'updated_at' => now()->subMinutes(10)]);
        $lock = Cache::lock('riot:run:'.$run->id, 120);
        $lock->get();
        $this->artisan('riot:recover')->assertSuccessful();
        Queue::assertNothingPushed();
        $lock->release();
    }

    public function test_wrong_identity_or_incomplete_payload_does_not_write_any_partida(): void
    {
        $conta = $this->account(attributes: ['puuid' => 'puuid-1']);
        foreach ([$this->matchPayload('other'), ['info' => ['queueId' => 420, 'participants' => [['puuid' => 'puuid-1']]]]] as $data) {
            try {
                app(ImportMatch::class)->execute($conta, 'BR1_BAD', $data);
                $this->fail('Expected invalid payload to be rejected.');
            } catch (RiotException) {
                $this->assertDatabaseCount('partidas', 0);
            }
        }
    }

    public function test_recovery_does_not_bypass_a_future_retry_after(): void
    {
        Queue::fake();
        $conta = $this->account();
        $conta->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)], 'active_conta_id' => $conta->id,
            'updated_at' => now()->subMinutes(10), 'available_at' => now()->addHour()]);
        $this->artisan('riot:recover')->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_real_database_worker_executes_a_serialized_job_with_safe_failure(): void
    {
        $conta = $this->account(attributes: ['puuid' => 'puuid-1', 'elo' => 'GOLD I', 'pdl' => 50]);
        $run = $conta->syncRuns()->create(['context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)], 'active_conta_id' => $conta->id]);
        Http::fake(['*' => Http::response([], 403)]);
        Queue::connection('database')->push(new SyncAccount($run->id, 0), '', 'riot');
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'riot',
            '--once' => true, '--sleep' => 0, '--tries' => 1])->assertSuccessful();
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame(50, $conta->fresh()->pdl);
        $this->assertDatabaseCount('jobs', 0);
    }
}
