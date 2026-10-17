<?php

namespace Tests\Feature;

use App\Actions\Sync\RefreshRankCutoffs;
use App\Enums\RiotRegion;
use App\Models\RankCutoff;
use App\Queries\RankProgress;
use App\Services\ReplaysCutoffSource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class RankProgressTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    private function page(array $overrides = []): string
    {
        $catalog = [];
        foreach (RiotRegion::cases() as $region) {
            $catalog[] = array_merge(['region' => strtoupper($region->value), 'grandmaster' => 900,
                'challenger' => 1600, 'timeFetched' => now()->getTimestampMs()], $overrides[$region->value] ?? []);
        }

        return '<html><h2>999999 LP</h2><script id="__NEXT_DATA__" type="application/json">'.
            json_encode(['query' => ['region' => 'BR', 'rank' => 'grandmaster'],
                'props' => ['pageProps' => ['cutoffData' => $catalog]]]).'</script></html>';
    }

    public static function ordinaryRanks(): array
    {
        return [
            ['IRON IV', 0, 0, 400, 'Bronze'],
            ['GOLD II', 50, 250, 150, 'Platina'],
            ['DIAMOND I', 99, 399, 1, 'Mestre'],
            ['SILVER I', 150, 400, 0, 'Ouro'],
        ];
    }

    #[DataProvider('ordinaryRanks')]
    public function test_divisions_and_lp_determine_progress_to_the_next_tier(string $rank, int $lp, int $current, int $remaining, string $next): void
    {
        $account = $this->account(attributes: ['elo' => $rank, 'pdl' => $lp, 'rank_synced_at' => now()]);
        $bar = app(RankProgress::class)->forAccount($account, collect())['bars'][0];
        $this->assertSame($current, $bar['current']);
        $this->assertSame($remaining, $bar['remaining']);
        $this->assertSame('Próximo elo: '.$next, $bar['label']);
        $this->assertLessThanOrEqual(100, $bar['percent']);
    }

    public function test_one_daily_public_request_updates_both_tiers_and_all_regions_without_a_riot_key(): void
    {
        config(['riot.key' => null]);
        Http::fake([ReplaysCutoffSource::URL => Http::response($this->page())]);
        $refresh = app(RefreshRankCutoffs::class);
        $this->assertSame('updated', $refresh->execute());
        $this->assertSame('cached', $refresh->execute());
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => ! $r->hasHeader('X-Riot-Token') && $r->url() === ReplaysCutoffSource::URL);
        $this->assertDatabaseCount('rank_cutoffs', 12);
        $this->assertDatabaseHas('rank_cutoffs', ['region' => 'br1', 'tier' => 'GRANDMASTER', 'lp' => 900]);
        $this->assertDatabaseHas('rank_cutoffs', ['region' => 'br1', 'tier' => 'CHALLENGER', 'lp' => 1600]);
    }

    public function test_failed_refresh_preserves_value_and_does_not_retry_with_every_page_view(): void
    {
        Http::fake([ReplaysCutoffSource::URL => Http::sequence()->push($this->page())->push('unavailable', 503)]);
        $refresh = app(RefreshRankCutoffs::class);
        $refresh->execute();
        $this->travel(1)->days();
        $this->assertSame('failed', $refresh->execute());
        $this->assertSame('cached', $refresh->execute());
        $cutoff = RankCutoff::where('region', 'br1')->where('tier', 'GRANDMASTER')->first();
        $this->assertSame(900, $cutoff->lp);
        $this->assertTrue($cutoff->isStale());
        Http::assertSentCount(2);
    }

    public function test_invalid_region_data_keeps_previous_pair_without_inventing_fallbacks(): void
    {
        Http::fake([ReplaysCutoffSource::URL => Http::sequence()->push($this->page())
            ->push($this->page(['br1' => ['grandmaster' => 1200, 'challenger' => 500]]))]);
        $refresh = app(RefreshRankCutoffs::class);
        $refresh->execute();
        $this->assertSame('partial', $refresh->execute(true));
        $this->assertDatabaseHas('rank_cutoffs', ['region' => 'br1', 'tier' => 'GRANDMASTER', 'lp' => 900]);
        $this->assertDatabaseHas('rank_cutoffs', ['region' => 'br1', 'tier' => 'CHALLENGER', 'lp' => 1600]);
    }

    public function test_changed_html_or_non_json_content_produces_unavailable_instead_of_extracting_random_lp(): void
    {
        Http::fake([ReplaysCutoffSource::URL => Http::response('<h2>600 LP</h2><p>Challenger 1000 LP</p>')]);
        $this->assertSame('failed', app(RefreshRankCutoffs::class)->execute());
        $this->assertSame(0, RankCutoff::whereNotNull('lp')->count());
        $account = $this->account(attributes: ['elo' => 'MASTER I', 'pdl' => 500, 'rank_synced_at' => now()]);
        foreach (app(RankProgress::class)->accounts(collect([$account]))->get($account->id)['bars'] as $bar) {
            $this->assertNull($bar['target']);
            $this->assertNull($bar['percent']);
        }
    }

    public function test_apex_bars_use_independent_targets_and_clamp_at_one_hundred_percent(): void
    {
        Http::fake([ReplaysCutoffSource::URL => Http::response($this->page())]);
        app(RefreshRankCutoffs::class)->execute();
        $account = $this->account(attributes: ['elo' => 'MASTER I', 'pdl' => 1000, 'rank_synced_at' => now()]);
        $bars = app(RankProgress::class)->accounts(collect([$account]))->get($account->id)['bars'];
        $this->assertSame(100, $bars[0]['percent']);
        $this->assertSame(100, $bars[0]['margin']);
        $this->assertCount(1, $bars);
        $this->assertSame('Corte Grão-Mestre', $bars[0]['label']);
        $account->elo = 'GRANDMASTER I';
        $bars = app(RankProgress::class)->forAccount($account, RankCutoff::where('region', 'br1')->get())['bars'];
        $this->assertCount(1, $bars);
        $this->assertSame('Corte Desafiante', $bars[0]['label']);
        $this->assertSame(62.5, $bars[0]['percent']);
        $this->assertSame(600, $bars[0]['remaining']);
        $account->elo = 'CHALLENGER I';
        $this->assertCount(0, app(RankProgress::class)->forAccount($account, RankCutoff::where('region', 'br1')->get())['bars']);
    }

    public function test_views_render_progress_without_network_and_identify_stale_source(): void
    {
        Http::fake([ReplaysCutoffSource::URL => Http::response($this->page())]);
        app(RefreshRankCutoffs::class)->execute();
        Http::fake();
        $account = $this->account(attributes: ['elo' => 'MASTER I', 'pdl' => 500, 'rank_synced_at' => now()]);
        $this->travel(2)->days();
        $this->actingAs($account->user)->get('/dashboard')->assertOk()
            ->assertSee('Corte Grão-Mestre')->assertSee('Referência desatualizada')->assertDontSee('@else');
        $this->get('/contas/'.$account->id)->assertOk()->assertSee('Corte Grão-Mestre')->assertDontSee('Corte Desafiante');
        Http::assertNothingSent();
    }

    public function test_refresh_lock_prevents_concurrent_scraping(): void
    {
        Http::fake();
        $lock = Cache::lock('rank-cutoffs:refresh', 120);
        $lock->get();
        try {
            $this->assertSame('busy', app(RefreshRankCutoffs::class)->execute());
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_unsynchronized_or_unranked_accounts_do_not_get_fabricated_progress(): void
    {
        $account = $this->account(attributes: ['elo' => 'UNRANKED', 'rank_synced_at' => now()]);
        $this->assertSame([], app(RankProgress::class)->forAccount($account, collect())['bars']);
        $account->elo = 'GOLD II';
        $account->rank_synced_at = null;
        $this->assertSame([], app(RankProgress::class)->forAccount($account, collect())['bars']);
    }

    public function test_next_brazilian_calendar_day_allows_the_scheduled_refresh_even_before_twenty_four_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16 20:00:00', 'UTC'));
        Http::fake([ReplaysCutoffSource::URL => Http::response($this->page())]);
        $refresh = app(RefreshRankCutoffs::class);
        $this->assertSame('updated', $refresh->execute());
        $this->travelTo(CarbonImmutable::parse('2026-10-17 06:10:00', 'UTC'));
        $this->assertSame('updated', $refresh->execute());
        Http::assertSentCount(2);
    }

    public function test_empty_response_is_a_controlled_failure_and_completed_normal_bar_renders_cleanly(): void
    {
        Http::fake([ReplaysCutoffSource::URL => Http::response('')]);
        $this->assertSame('failed', app(RefreshRankCutoffs::class)->execute());
        $account = $this->account(attributes: ['elo' => 'DIAMOND I', 'pdl' => 100, 'rank_synced_at' => now()]);
        $this->actingAs($account->user)->get('/dashboard')->assertOk()
            ->assertSee('Progresso de LP concluído')->assertDontSee('@else');
    }
}
