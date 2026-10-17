<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-17 15:00:00', 'UTC'));
    }

    public function test_dashboard_aggregates_matches_and_metric_samples_instead_of_account_averages(): void
    {
        $user = User::factory()->create();
        $a = $this->account($user, ['puuid' => 'a']);
        $b = $this->account($user, ['puuid' => 'b', 'riot_id' => 'Segunda#BR1']);
        $this->matchRecord($a, ['kills' => 10, 'deaths' => 2, 'assists' => 4, 'damage' => 100,
            'cs' => 100, 'duration_seconds' => 1000, 'vision_score' => null]);
        for ($i = 0; $i < 3; $i++) {
            $this->matchRecord($b, ['win' => false, 'kills' => 2, 'deaths' => 4, 'assists' => 2,
                'damage' => 300, 'cs' => 200, 'duration_seconds' => 2000, 'vision_score' => 20,
                'champion_name' => 'Lux', 'opponent_champion_name' => 'Zed', 'lane' => 'TOP']);
        }
        $foreign = $this->account(attributes: ['puuid' => 'foreign']);
        $this->matchRecord($foreign, ['damage' => 999999]);
        $this->matchRecord($a, ['damage' => 999999, 'identity_puuid' => null]);
        $this->matchRecord($a, ['damage' => 999999, 'is_remake' => true]);
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['games'] === 4 && $s['winrate'] === 25.0
                && $s['kills'] === 16 && $s['deaths'] === 14 && $s['assists'] === 10 && $s['kda'] === '1.86')
            ->assertViewHas('insight', fn ($s) => $s['sums']['damage'] === 1000
                && $s['averages']['damage']['value'] === 250.0 && $s['averages']['vision_score']['sample'] === 3
                && $s['averages']['cs_per_minute']['value'] === 6.0)
            ->assertViewHas('champions', fn ($rows) => $rows->count() === 2 && $rows->sum('games') === 4)
            ->assertSee('Combate')->assertSee('Economia')->assertSee('Visão')
            ->assertSee('aria-label="Estatísticas de todas as contas"', false);
        foreach (['overview', 'history', 'champions', 'matchups', 'patterns', 'records', 'progress'] as $tab) {
            $this->get('/dashboard?tab='.$tab)->assertOk()->assertViewHas('summary', fn ($s) => $s['games'] === 4);
        }
        $this->get('/dashboard?tab=history')->assertSee('Segunda#BR1');
        $this->get('/dashboard?tab=matchups')->assertSee('Confrontos: Topo')->assertSee('opponent=Zed', false);
        Http::assertNothingSent();
    }

    public function test_dashboard_filters_apply_to_all_accounts_and_keep_links_in_global_context(): void
    {
        $user = User::factory()->create();
        $a = $this->account($user, ['puuid' => 'a']);
        $b = $this->account($user, ['puuid' => 'b', 'riot_id' => 'Outra#BR1']);
        foreach ([$a, $b] as $account) {
            $this->matchRecord($account, ['queue_id' => 420, 'lane' => 'TOP', 'champion_name' => 'Lux', 'opponent_champion_name' => 'Zed']);
            $this->matchRecord($account, ['queue_id' => 440]);
            $this->matchRecord($account, ['queue_id' => 420, 'lane' => 'TOP', 'champion_name' => 'Lux', 'played_at' => now()->subDays(40)]);
        }
        $url = '/dashboard?tab=matchups&queue=420&role=TOP&champion=Lux&opponent=Zed&result=win&days=7';
        $this->actingAs($user)->get($url)->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['games'] === 2)
            ->assertViewHas('matchups', fn ($g) => $g['TOP'][0]['games'] === 2)
            ->assertSee('dashboard?tab=history', false)->assertSee('queue=420', false);
        $this->get('/dashboard?queue=420&result=loss')->assertOk()->assertViewHas('summary', fn ($s) => $s['games'] === 0);
        Season::current()->forceFill(['starts_at' => now()->subDays(2), 'regional_starts' => null])->save();
        $this->get('/dashboard?queue=420&days=0')->assertOk()->assertViewHas('summary', fn ($s) => $s['games'] === 2);
        foreach (['queue=999', 'tab=invalid', 'role=ADMIN', 'result=other'] as $invalid) {
            $this->get('/dashboard?'.$invalid)->assertSessionHasErrors();
        }
    }

    public function test_shared_match_counts_each_owned_account_once_and_pages_identify_the_source_account(): void
    {
        $user = User::factory()->create();
        $a = $this->account($user, ['puuid' => 'a', 'riot_id' => 'Primeira#BR1']);
        $b = $this->account($user, ['puuid' => 'b', 'riot_id' => 'Segunda#BR1']);
        $this->matchRecord($a, ['match_id' => 'BR1_SHARED', 'win' => true]);
        $this->matchRecord($b, ['match_id' => 'BR1_SHARED', 'win' => false]);
        $this->actingAs($user)->get('/dashboard?tab=history')->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['games'] === 2 && $s['winrate'] === 50.0)
            ->assertViewHas('partidas', fn ($p) => $p->total() === 2 && $p->every(fn ($m) => $m->relationLoaded('conta')))
            ->assertSee('Participação desta conta')->assertSee('Primeira#BR1')->assertSee('Segunda#BR1');
        $this->get('/dashboard?tab=records')->assertOk()->assertSee('Primeira#BR1');
        for ($i = 0; $i < 25; $i++) {
            $this->matchRecord($b);
        }
        $this->get('/dashboard?tab=history&queue=420&days=7&page=2')->assertOk()
            ->assertViewHas('partidas', fn ($p) => $p->total() === 27 && $p->count() === 7
                && str_contains($p->previousPageUrl(), 'tab=history') && str_contains($p->previousPageUrl(), 'days=7'));
    }

    public function test_empty_dashboard_and_rank_evolution_keep_accounts_separate_and_exclude_foreign_snapshots(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['overview', 'history', 'champions', 'matchups', 'patterns', 'records', 'progress'] as $tab) {
            $this->get('/dashboard?tab='.$tab)->assertOk()->assertViewHas('summary', fn ($s) => $s['games'] === 0);
        }
        $a = $this->account($user, ['puuid' => 'a']);
        $b = $this->account($user, ['puuid' => 'b', 'riot_id' => 'Segunda#BR1']);
        $foreign = $this->account(attributes: ['puuid' => 'foreign']);
        foreach ([$a, $b, $foreign] as $account) {
            $account->rankSnapshots()->create(['tier' => 'MASTER', 'division' => 'I', 'lp' => 50,
                'wins' => 1, 'losses' => 0, 'observed_at' => now()]);
        }
        $this->get('/dashboard?tab=progress')->assertOk()->assertSee('Evolução de ranking por conta')
            ->assertViewHas('rankHistories', fn ($rows) => $rows->count() === 2 && $rows->every(fn ($row) => count($row['points']) === 1 && $row['points'][0]['value'] === 2850));
        for ($i = 0; $i < 65; $i++) {
            $a->rankSnapshots()->create(['tier' => 'MASTER', 'division' => 'I', 'lp' => 50,
                'wins' => 1, 'losses' => 0, 'observed_at' => now()->subMinutes($i + 1)]);
        }
        $this->get('/dashboard?tab=progress')->assertOk()
            ->assertViewHas('rankHistories', fn ($rows) => count($rows->first()['points']) === 60 && count($rows->last()['points']) === 1);
        Http::assertNothingSent();
    }
}
