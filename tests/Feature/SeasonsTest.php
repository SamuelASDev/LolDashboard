<?php

namespace Tests\Feature;

use App\Actions\Seasons\ActivateSeason;
use App\Actions\Sync\AdvanceSync;
use App\Actions\Sync\ImportMatch;
use App\Actions\Sync\StartSync;
use App\Enums\RiotRegion;
use App\Models\Season;
use App\Models\User;
use App\Queries\AccountStatistics;
use App\Support\HistoryWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class SeasonsTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    public function test_2026_is_the_only_initial_season_and_current_default(): void
    {
        $this->assertDatabaseCount('seasons', 1);
        $this->assertSame('2026', Season::current()->name);
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()
            ->assertViewHas('season', fn ($season) => $season->name === '2026');
    }

    public function test_only_verified_administrators_can_manage_seasons(): void
    {
        $draft = Season::create(['name' => '2027']);
        $this->get('/admin/temporadas')->assertRedirect('/login');
        foreach (['user', 'mod'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/admin/temporadas')->assertForbidden();
            $this->post('/admin/temporadas', ['name' => 'Outra'])->assertForbidden();
            $this->post("/admin/temporadas/{$draft->id}/iniciar")->assertForbidden();
        }
        $this->actingAs(User::factory()->unverified()->create(['role' => 'admin']))
            ->post("/admin/temporadas/{$draft->id}/iniciar")->assertRedirect('/verify-email');
        $this->assertSame('2026', Season::current()->name);
    }

    public function test_admin_registers_then_switches_atomically_preserving_all_matches(): void
    {
        $this->freezeTime();
        $account = $this->account(User::factory()->create(['role' => 'admin']), ['puuid' => 'p']);
        $match = $this->matchRecord($account);
        $old = Season::current();
        $this->actingAs($account->user)->post('/admin/temporadas', ['name' => '2027'])->assertRedirect('/admin/temporadas');
        $draft = Season::where('name', '2027')->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertSame($old->id, Season::current()->id);
        $this->post("/admin/temporadas/{$draft->id}/iniciar")->assertRedirect('/admin/temporadas');
        $old->refresh();
        $new = Season::current();
        $this->assertSame($draft->id, $new->id);
        $this->assertSame('ended', $old->status);
        $this->assertDatabaseHas('audit_events', ['action' => 'season.activated', 'actor_id' => $account->user_id]);
        $this->assertTrue($old->ends_at->equalTo($new->starts_at));
        $this->assertDatabaseHas('partidas', ['id' => $match->id]);
        $this->assertSame(1, Season::whereNotNull('active_slot')->count());
        $this->post("/admin/temporadas/{$old->id}/iniciar")->assertSessionHasErrors('season');
        $this->post("/admin/temporadas/{$new->id}/iniciar")->assertSessionHasErrors('season');
        $this->assertSame($new->id, Season::current()->id);
    }

    public function test_names_and_season_filters_are_validated_and_drafts_cannot_import(): void
    {
        Queue::fake();
        $account = $this->account(User::factory()->create(['role' => 'admin']));
        $draft = Season::create(['name' => '2027']);
        $this->actingAs($account->user)->post('/admin/temporadas', ['name' => '2026'])->assertSessionHasErrors('name');
        $this->post('/admin/temporadas', ['name' => ''])->assertSessionHasErrors('name');
        $this->get('/dashboard?season=99999')->assertSessionHasErrors('season');
        $this->get('/dashboard?season='.$draft->id)->assertSessionHasErrors('season');
        $this->post('/contas/'.$account->id.'/sync', ['season' => $draft->id])->assertSessionHasErrors('season');
        $this->assertDatabaseCount('sync_runs', 0);
        Queue::assertNothingPushed();
    }

    public function test_period_boundaries_filter_all_statistics_without_crossing_ownership(): void
    {
        $this->freezeTime();
        $old = Season::current();
        $next = Season::create(['name' => '2027']);
        app(ActivateSeason::class)->execute($next);
        $next->refresh();
        $account = $this->account(attributes: ['puuid' => 'p']);
        $other = $this->account(attributes: ['puuid' => 'other']);
        // The first game started before the switch and finished after it.
        $this->matchRecord($account, ['champion_name' => 'Ahri', 'started_at' => now()->subMinute(), 'played_at' => now()->addMinutes(20)]);
        $this->matchRecord($account, ['champion_name' => 'Lux', 'started_at' => $next->starts_at, 'played_at' => now()->addMinutes(30)]);
        $this->matchRecord($other, ['champion_name' => 'Jinx', 'started_at' => $next->starts_at]);
        $stats = app(AccountStatistics::class);
        $this->assertSame(1, $stats->summary($stats->base($account->user, 0, null, 420, $old->fresh()))['games']);
        $this->actingAs($account->user)->get('/dashboard?days=0')->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['games'] === 1)
            ->assertViewHas('champions', fn ($rows) => $rows->pluck('champion_name')->all() === ['Lux']);
        $this->get("/contas/{$account->id}?days=0&tab=history&season={$old->id}")->assertOk()
            ->assertViewHas('partidas', fn ($p) => $p->total() === 1 && $p->first()->champion_name === 'Ahri')
            ->assertSee('compact: true', false);
        $this->get("/contas/{$other->id}?season={$old->id}")->assertNotFound();
    }

    public function test_archived_import_uses_its_own_bounds_and_default_import_uses_current(): void
    {
        Queue::fake();
        $this->freezeTime();
        $old = Season::current();
        app(ActivateSeason::class)->execute(Season::create(['name' => '2027']));
        $this->travel(1)->hours();
        $account = $this->account(attributes: ['puuid' => 'p']);
        $this->actingAs($account->user)->post("/contas/{$account->id}/sync", ['season' => $old->id, 'mode' => 'full'])
            ->assertRedirect("/contas/{$account->id}?season={$old->id}");
        $run = $account->syncRuns()->firstOrFail();
        $this->assertSame($old->id, $run->context['history_window']['season_id']);
        $this->assertSame($old->fresh()->ends_at->getTimestamp(), $run->context['history_window']['end']);
        $run->finish('cancelled', 'Test');
        $newRun = app(StartSync::class)->execute($account);
        $this->assertSame(Season::current()->id, $newRun->context['history_window']['season_id']);
    }

    public function test_import_rechecks_closed_season_even_with_a_stale_open_window(): void
    {
        $this->freezeTime();
        $account = $this->account(attributes: ['puuid' => 'p']);
        $old = Season::current();
        $window = HistoryWindow::forRegion(RiotRegion::BR);
        // Simulates a stale producer with a window extending beyond the switch.
        $window['end'] += 3600;
        app(ActivateSeason::class)->execute(Season::create(['name' => '2027']));
        $payload = $this->matchPayload('p', info: ['gameStartTimestamp' => now()->getTimestampMs()]);
        $this->assertFalse(app(ImportMatch::class)->execute($account, 'BR1_NEW', $payload, $window));
        $this->assertDatabaseCount('partidas', 0);
        $this->travel(1)->hours();
        $this->assertTrue(app(ImportMatch::class)->execute($account, 'BR1_NEW', $payload));
        $this->assertDatabaseHas('partidas', ['match_id' => 'BR1_NEW', 'started_at' => $old->fresh()->ends_at->toDateTimeString()]);
    }

    public function test_pending_run_clamps_its_window_after_the_season_switch(): void
    {
        $this->freezeTime();
        $account = $this->account(attributes: ['puuid' => 'p']);
        $window = HistoryWindow::forRegion(RiotRegion::BR);
        $window['end'] += 3600;
        $run = $account->syncRuns()->create(['active_conta_id' => $account->id, 'phase' => 'history',
            'context' => ['history_window' => $window]]);
        app(ActivateSeason::class)->execute(Season::create(['name' => '2027']));
        Http::fake(['*/ids*' => Http::response([])]);
        app(AdvanceSync::class)->execute($run);
        Http::assertSent(fn ($r) => $r['endTime'] === Season::where('name', '2026')->first()->ends_at->getTimestamp());
        $this->assertSame('completed', $run->status);
    }

    public function test_snapshot_chart_is_restricted_to_the_selected_season(): void
    {
        $this->freezeTime();
        $account = $this->account(attributes: ['puuid' => 'p']);
        $old = Season::current();
        $data = ['queue_id' => 420, 'tier' => 'GOLD', 'division' => 'II', 'lp' => 40, 'wins' => 1, 'losses' => 0];
        $account->rankSnapshots()->create($data + ['observed_at' => now()->subHour()]);
        app(ActivateSeason::class)->execute(Season::create(['name' => '2027']));
        $account->rankSnapshots()->create($data + ['observed_at' => now()]);
        $this->actingAs($account->user)->get("/contas/{$account->id}?days=0&tab=progress&season={$old->id}")
            ->assertOk()->assertViewHas('snapshots', fn ($rows) => $rows->count() === 1 && $rows->first()->observed_at->lessThan($old->fresh()->ends_at));
        $this->get("/contas/{$account->id}?days=0&tab=progress")->assertOk()
            ->assertViewHas('snapshots', fn ($rows) => $rows->count() === 1 && $rows->first()->observed_at->equalTo(Season::current()->starts_at));
    }

    public function test_additive_migration_backfills_start_without_removing_existing_matches(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Artisan::call('migrate:rollback', ['--step' => 3, '--force' => true]);
        $account = $this->account(attributes: ['puuid' => 'p']);
        $match = $this->matchRecord($account, ['played_at' => '2026-01-08 15:10:00', 'duration_seconds' => 1800]);
        Artisan::call('migrate', ['--force' => true]);
        $this->assertDatabaseCount('partidas', 1);
        $this->assertDatabaseHas('partidas', ['id' => $match->id, 'started_at' => '2026-01-08 14:40:00']);
        $this->assertSame('2026', Season::current()->name);
    }
}
