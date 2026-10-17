<?php

// Evidência diagnóstica da revisão de 17/10/2026, fora da suíte de regressão.
// Estes testes afirmam os problemas atuais: passar confirma a reprodução, não a correção.
// Executar explicitamente via PHPUnit; a configuração exige SQLite em memória e notificações simuladas.

namespace Tests\Review;

use App\Actions\Sync\AdvanceSync;
use App\Actions\Sync\ImportMatch;
use App\Enums\RiotRegion;
use App\Models\User;
use App\Queries\AccountDecay;
use App\Support\HistoryWindow;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class ProjectReviewProbeTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    protected function setUp(): void
    {
        // Guarda anterior ao parent: RefreshDatabase pode executar migrations durante o setUp.
        if (($_ENV['DB_CONNECTION'] ?? null) !== 'sqlite' || ($_ENV['DB_DATABASE'] ?? null) !== ':memory:'
            || is_file(__DIR__.'/../../../bootstrap/cache/config.php')) {
            throw new \RuntimeException('Diagnóstico recusado: exige SQLite em memória e configuração sem cache.');
        }
        parent::setUp();
    }

    public function test_review_registration_accepts_twelve_accounts_from_one_client(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Notification::fake();
        for ($i = 0; $i < 12; $i++) {
            $this->post('/register', ['name' => 'Review', 'email' => 'review'.$i.'@example.test',
                'password' => 'password', 'password_confirmation' => 'password'])->assertRedirect('/verify-email');
            $this->post('/logout')->assertRedirect();
        }
        $this->assertDatabaseCount('users', 12);
        Notification::assertSentTimes(VerifyEmail::class, 12);
        Http::assertNothingSent();
    }

    public function test_review_reset_password_responses_disclose_whether_email_exists(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'exists@example.test']);
        $this->post('/forgot-password', ['email' => 'exists@example.test'])->assertSessionHas('status')->assertSessionHasNoErrors();
        $this->post('/forgot-password', ['email' => 'absent@example.test'])->assertSessionHasErrors('email');
    }

    public function test_review_one_bulk_sync_request_creates_twenty_five_active_runs(): void
    {
        Queue::fake();
        config(['riot.queue_connection' => 'database']);
        $user = User::factory()->create();
        for ($i = 0; $i < 25; $i++) {
            $this->account($user, ['riot_id' => 'Review'.$i.'#BR1']);
        }
        $this->actingAs($user)->post('/contas/sync-all')->assertRedirect();
        $this->assertDatabaseCount('sync_runs', 25);
        $this->assertSame(25, DB::table('sync_runs')->whereNotNull('active_conta_id')->count());
        Http::assertNothingSent();
    }

    public function test_review_flex_completion_removes_the_stale_solo_decay_warning(): void
    {
        $account = $this->account(attributes: ['puuid' => 'review', 'elo' => 'MASTER I',
            'rank_synced_at' => now(), 'history_synced_at' => now()->subWeek()]);
        $this->assertTrue(app(AccountDecay::class)->accounts(collect([$account]))->get($account->id)['stale']);
        $run = $account->syncRuns()->create(['queue_id' => 440, 'phase' => 'history', 'mode' => 'full',
            'active_conta_id' => $account->id, 'context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)]]);
        Http::fake(['*/ids*' => Http::response([])]);
        app(AdvanceSync::class)->execute($run);
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertFalse(app(AccountDecay::class)->accounts(collect([$account->fresh()]))->get($account->id)['stale']);
        $this->assertDatabaseCount('partidas', 0);
    }

    public function test_review_incomplete_match_payload_can_replace_a_complete_shared_scoreboard(): void
    {
        $account = $this->account(attributes: ['puuid' => 'review']);
        $players = [];
        foreach (range(1, 10) as $slot) {
            $players[] = ['participantId' => $slot, 'puuid' => $slot === 1 ? 'review' : 'player-'.$slot,
                'teamId' => $slot <= 5 ? 100 : 200, 'teamPosition' => 'MIDDLE', 'championName' => 'Ahri',
                'win' => true, 'kills' => 1, 'deaths' => 1, 'assists' => 1];
        }
        $importer = app(ImportMatch::class);
        $importer->execute($account, 'BR1_REVIEW', $this->matchPayload(info: ['participants' => $players]));
        $this->assertDatabaseCount('match_participants', 10);
        $importer->execute($account, 'BR1_REVIEW', $this->matchPayload('review', ['participantId' => 1, 'teamId' => 100]));
        $this->assertDatabaseCount('match_participants', 1);
        $this->assertDatabaseCount('partidas', 1);
        Http::assertNothingSent();
    }

    public function test_review_champions_tab_fetches_scoreboard_rows_and_all_match_insights(): void
    {
        $account = $this->account(attributes: ['puuid' => 'review']);
        app(ImportMatch::class)->execute($account, 'BR1_COST', $this->matchPayload('review'));
        DB::enableQueryLog();
        $this->actingAs($account->user)->get('/dashboard?tab=champions')->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        $this->assertTrue(collect($queries)->contains(fn ($sql) => str_contains($sql, '"match_participants"') && ! str_contains($sql, 'join')));
        $this->assertTrue(collect($queries)->contains(fn ($sql) => str_contains($sql, 'select "partidas".*') && ! str_contains($sql, 'limit')));
    }
}
