<?php

namespace Tests\Feature;

use App\Models\RankSnapshot;
use App\Queries\AccountMatchups;
use App\Queries\AccountStatistics;
use App\Support\PersonalCharts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class AnalyticsLayoutTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    public function test_same_opponent_is_kept_separate_by_position_and_other_users_are_excluded(): void
    {
        $account = $this->account(attributes: ['puuid' => 'p']);
        foreach ([['TOP', true], ['TOP', false], ['MIDDLE', true]] as [$role, $win]) {
            $this->matchRecord($account, ['lane' => $role, 'win' => $win, 'opponent_champion_name' => 'Lux',
                'opponent_champion_id' => 99, 'cs' => 180, 'duration_seconds' => 1800]);
        }
        $other = $this->account(attributes: ['puuid' => 'other']);
        $this->matchRecord($other, ['lane' => 'TOP', 'opponent_champion_name' => 'Zed']);
        $this->matchRecord($account, ['lane' => 'TOP', 'opponent_champion_name' => 'Zed', 'identity_puuid' => null]);
        $base = app(AccountStatistics::class)->base($account->user, 30, $account);
        $groups = app(AccountMatchups::class)->grouped($base);
        $this->assertCount(2, $groups);
        $this->assertSame(2, $groups['TOP'][0]['games']);
        $this->assertSame(50.0, $groups['TOP'][0]['winrate']);
        $this->assertSame(1, $groups['MIDDLE'][0]['games']);
        $this->assertSame('Lux', $groups['TOP'][0]['champion_name']);
        $this->assertSame(99, $groups['TOP'][0]['champion_id']);
    }

    public function test_matchup_page_filters_roles_and_links_to_the_correct_history(): void
    {
        Http::fake();
        $account = $this->account(attributes: ['puuid' => 'p']);
        $this->matchRecord($account, ['lane' => 'TOP', 'opponent_champion_name' => 'Aatrox', 'opponent_champion_id' => 266]);
        $this->matchRecord($account, ['lane' => 'MIDDLE', 'opponent_champion_name' => 'Zed', 'opponent_champion_id' => 238]);
        $this->actingAs($account->user)->get('/contas/'.$account->id.'?tab=matchups&role=TOP')
            ->assertOk()->assertSee('Confrontos por posição')->assertSee('Confrontos: Topo')
            ->assertDontSee('Confrontos: Meio')->assertSee('Aatrox.png', false)->assertSee('opponent=Aatrox', false);
        $this->get('/contas/'.$account->id.'?tab=progress')->assertOk()->assertSee('Resultados por dia');
        Http::assertNothingSent();
    }

    public function test_chart_points_limit_days_and_never_convert_unranked_into_zero_rank(): void
    {
        $days = [];
        foreach (range(1, 30) as $day) {
            $days[sprintf('%02d/09/2026', $day)] = ['games' => 3, 'wins' => 2];
        }
        $points = PersonalCharts::daily($days);
        $this->assertCount(21, $points);
        $this->assertSame('10/09', $points[0]['label']);
        $this->assertSame(1, $points[0]['losses']);
        $snapshots = collect([
            new RankSnapshot(['tier' => 'UNRANKED', 'division' => null, 'lp' => 0, 'observed_at' => now()]),
            new RankSnapshot(['tier' => 'DIAMOND', 'division' => 'II', 'lp' => 50, 'observed_at' => now()]),
        ]);
        $rank = PersonalCharts::ranking($snapshots);
        $this->assertCount(1, $rank);
        $this->assertSame(2650, $rank[0]['value']);
        $this->assertStringContainsString('DIAMOND II', $rank[0]['title']);
    }

    public function test_empty_account_pages_render_without_fake_charts_or_matchups(): void
    {
        Http::fake();
        $account = $this->account();
        foreach (['overview', 'matchups', 'progress'] as $tab) {
            $this->actingAs($account->user)->get('/contas/'.$account->id.'?tab='.$tab)->assertOk();
        }
        Http::assertNothingSent();
    }
}
