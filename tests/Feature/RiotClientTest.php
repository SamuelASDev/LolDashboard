<?php

namespace Tests\Feature;

use App\Enums\RiotRegion;
use App\Exceptions\RiotException;
use App\Services\RiotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RiotClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_regions_and_encoded_identifiers_use_fixed_hosts_and_header_auth(): void
    {
        Http::fake(['*' => Http::response(['puuid' => 'p'])]);
        $client = app(RiotService::class);
        $client->account('Nome / Com espaço#BR1', RiotRegion::EUW);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://europe.api.riotgames.com/')
            && str_contains($request->url(), 'Nome%20%2F%20Com%20espa%C3%A7o')
            && ! str_contains($request->url(), 'test-key') && $request->hasHeader('X-Riot-Token', 'test-key'));
    }

    public function test_each_supported_platform_routes_match_history_to_its_region(): void
    {
        Http::fake(['*' => Http::response([])]);
        foreach (RiotRegion::cases() as $region) {
            app(RiotService::class)->matchIds('p', $region, 20);
            Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://'.$region->routing().'.api.riotgames.com/')
                && $r['start'] === 20 && $r['queue'] === 420);
        }
        Http::assertSentCount(6);
    }

    public function test_permanent_errors_are_safe_and_not_retried_inside_the_client(): void
    {
        foreach ([403, 404] as $status) {
            Http::fake(['*' => Http::response(['secret' => 'never display this'], $status)]);
            try {
                app(RiotService::class)->rank('p', RiotRegion::BR);
                $this->fail('Expected a controlled exception.');
            } catch (RiotException $e) {
                $this->assertFalse($e->retryable);
                $this->assertStringNotContainsString('never display', $e->getMessage());
                $this->assertStringNotContainsString('test-key', $e->getMessage());
            }
            Http::assertSentCount(1);
        }
    }

    public function test_rate_limit_preserves_retry_after(): void
    {
        Http::fake(['*' => Http::response([], 429, ['Retry-After' => '180'])]);
        try {
            app(RiotService::class)->rank('p', RiotRegion::BR);
            $this->fail('Expected a rate limit.');
        } catch (RiotException $e) {
            $this->assertTrue($e->retryable);
            $this->assertSame(180, $e->retryAfter);
        }
        Http::assertSentCount(1);
    }

    public function test_missing_key_fails_before_network_and_redirects_are_not_followed(): void
    {
        Http::fake(['*' => Http::response([], 302, ['Location' => 'https://attacker.invalid'])]);
        try {
            app(RiotService::class)->rank('p', RiotRegion::BR);
            $this->fail('Redirects must not be accepted.');
        } catch (RiotException $e) {
            $this->assertFalse($e->retryable);
        }
        Http::assertSentCount(1);
        config(['riot.key' => null]);
        $this->expectException(RiotException::class);
        app(RiotService::class)->rank('p', RiotRegion::BR);
    }

    public function test_local_shared_budget_defers_requests_before_network(): void
    {
        config(['riot.requests_per_second' => 1]);
        Http::fake(['*' => Http::response([])]);
        $client = app(RiotService::class);
        $client->rank('p', RiotRegion::BR);
        try {
            $client->rank('p', RiotRegion::BR);
            $this->fail('Budget should be exhausted.');
        } catch (RiotException $e) {
            $this->assertTrue($e->throttled);
        }
        Http::assertSentCount(1);
    }

    public function test_empty_league_is_valid_but_malformed_payload_is_not(): void
    {
        Http::fakeSequence()->push([])->push(['unexpected' => 'data']);
        $this->assertNull(app(RiotService::class)->rank('p', RiotRegion::BR));
        $this->expectException(RiotException::class);
        app(RiotService::class)->rank('p', RiotRegion::BR);
    }

    public function test_zero_game_rank_is_a_valid_response(): void
    {
        Http::fake(['*' => Http::response([['queueType' => 'RANKED_SOLO_5x5', 'tier' => 'GOLD',
            'rank' => 'I', 'leaguePoints' => 0, 'wins' => 0, 'losses' => 0]])]);
        $this->assertSame(0, app(RiotService::class)->rank('p', RiotRegion::BR)['wins']);
    }

    public function test_a_malformed_league_list_is_not_mistaken_for_unranked(): void
    {
        Http::fake(['*' => Http::response(['not-a-league'])]);
        $this->expectException(RiotException::class);
        app(RiotService::class)->rank('p', RiotRegion::BR);
    }
}
