<?php

namespace Tests\Feature;

use App\Actions\Accounts\SaveAccount;
use App\Actions\Sync\AdvanceSync;
use App\Actions\Sync\ImportMatch;
use App\Actions\Sync\StartSync;
use App\Enums\RiotRegion;
use App\Exceptions\RiotException;
use App\Jobs\SyncAccount;
use App\Models\RiotMatch;
use App\Models\Season;
use App\Models\User;
use App\Queries\AccountDecay;
use App\Support\HistoryWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class ReviewHardeningTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    public function test_registration_and_password_recovery_have_ip_throttles(): void
    {
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [])->assertSessionHasErrors();
        }
        $this->post('/register', [])->assertTooManyRequests();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertTooManyRequests();
    }

    public function test_password_recovery_uses_same_generic_response_for_known_unknown_and_throttled_recipient(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors()->assertSessionHas('status');
        $message = session('status');
        $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertSessionHasNoErrors()->assertSessionHas('status', $message);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors()->assertSessionHas('status', $message);
    }

    public function test_batch_sync_respects_per_user_active_quota_and_keeps_existing_runs(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        foreach (range(1, 8) as $n) {
            $this->account($user, ['riot_id' => 'Player'.$n.'#BR1']);
        }
        $this->actingAs($user)->post('/contas/sync-all')->assertRedirect('/dashboard');
        $this->assertDatabaseCount('sync_runs', 3);
        Queue::assertPushed(SyncAccount::class, 3);
        $this->post('/contas/sync-all')->assertRedirect('/dashboard');
        $this->assertDatabaseCount('sync_runs', 3);
    }

    public function test_daily_sync_and_account_limits_are_enforced_in_actions(): void
    {
        Queue::fake();
        config(['workspace.max_accounts' => 1, 'workspace.daily_recent_syncs' => 1]);
        $account = $this->account();
        try {
            app(SaveAccount::class)->execute($account->user, ['nick' => 'Nova', 'riot_id' => 'Outra#BR1', 'regiao' => 'br1']);
            $this->fail('Account quota was bypassed');
        } catch (ValidationException) {
            $this->assertDatabaseCount('contas', 1);
        }
        $run = app(StartSync::class)->execute($account);
        $run->finish('completed', 'Teste');
        $this->expectException(ValidationException::class);
        app(StartSync::class)->execute($account);
    }

    public function test_partial_duplicate_and_invalid_team_payloads_preserve_complete_cache(): void
    {
        $account = $this->account(attributes: ['puuid' => 'owner']);
        $payload = $this->matchPayload('owner');
        app(ImportMatch::class)->execute($account, 'BR1_COMPLETE', $payload);
        foreach (['partial', 'duplicate', 'team'] as $case) {
            $bad = $payload;
            if ($case === 'partial') {
                $bad['info']['participants'] = [$bad['info']['participants'][0]];
            } elseif ($case === 'duplicate') {
                $bad['info']['participants'][1]['puuid'] = 'owner';
            } else {
                $bad['info']['participants'][1]['teamId'] = 200;
            }
            try {
                app(ImportMatch::class)->execute($account, 'BR1_COMPLETE', $bad);
                $this->fail('Incomplete payload accepted');
            } catch (RiotException) {
                $this->assertDatabaseCount('match_participants', 10);
                $this->assertDatabaseCount('partidas', 1);
                $this->assertSame($payload, RiotMatch::first()->payload);
            }
        }
    }

    public function test_only_current_solo_coverage_can_clear_decay_staleness(): void
    {
        $account = $this->account(attributes: ['puuid' => 'owner', 'elo' => 'MASTER I', 'rank_synced_at' => now()]);
        $window = HistoryWindow::forRegion(RiotRegion::BR);
        Http::fake(['*/ids*' => Http::response([])]);
        $legacy = $account->syncRuns()->create(['queue_id' => 440, 'phase' => 'history', 'active_conta_id' => $account->id, 'context' => ['history_window' => $window]]);
        app(AdvanceSync::class)->execute($legacy);
        $this->assertSame('cancelled', $legacy->fresh()->status);
        $this->assertNull($account->fresh()->solo_history_synced_at);
        $current = $account->syncRuns()->create(['queue_id' => 420, 'mode' => 'full', 'phase' => 'history', 'active_conta_id' => $account->id, 'context' => ['history_window' => $window]]);
        app(AdvanceSync::class)->execute($current);
        $this->assertSame('completed', $current->fresh()->status);
        $this->assertFalse(app(AccountDecay::class)->accounts(collect([$account->fresh()]))->get($account->id)['stale']);
        $account->refresh()->forceFill(['solo_history_synced_at' => null, 'solo_history_window' => null])->save();
        $old = Season::create(['name' => '2025', 'status' => 'ended', 'starts_at' => '2025-01-01', 'ends_at' => '2026-01-01']);
        $oldWindow = HistoryWindow::forRegion(RiotRegion::BR, $old);
        $run = $account->syncRuns()->create(['queue_id' => 420, 'mode' => 'full', 'phase' => 'history', 'active_conta_id' => $account->id, 'context' => ['history_window' => $oldWindow]]);
        app(AdvanceSync::class)->execute($run);
        $this->assertNull($account->fresh()->solo_history_synced_at);
        $this->assertTrue(app(AccountDecay::class)->accounts(collect([$account->fresh()]))->get($account->id)['stale']);
    }
}
