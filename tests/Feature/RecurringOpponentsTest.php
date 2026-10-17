<?php

namespace Tests\Feature;

use App\Actions\Sync\ImportMatch;
use App\Models\Conta;
use App\Models\RiotMatch;
use App\Models\Season;
use App\Models\User;
use App\Queries\AccountInsights;
use App\Queries\AccountStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class RecurringOpponentsTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-17 15:00:00', 'UTC'));
    }

    private function game(Conta $account, string $id, array $rivals = [], array $info = [], array $owner = []): void
    {
        $players = [array_merge(['puuid' => $account->puuid, 'teamId' => 100, 'teamPosition' => 'MIDDLE'], $owner),
            ['puuid' => 'ally', 'teamId' => 100, 'teamPosition' => 'TOP', 'riotIdGameName' => 'Aliado']];
        $players = array_merge($players, $rivals ?: [
            ['puuid' => 'enemy-mid', 'teamId' => 200, 'teamPosition' => 'MIDDLE', 'riotIdGameName' => 'AdversarioMid'],
            ['puuid' => 'enemy-jungle', 'teamId' => 200, 'teamPosition' => 'JUNGLE', 'riotIdGameName' => 'AdversarioSelva'],
        ]);
        foreach ($players as $index => &$player) {
            $player += ['participantId' => $index + 1, 'championName' => 'Ahri', 'win' => $player['teamId'] === 100,
                'kills' => 5, 'deaths' => 2, 'assists' => 7, 'riotIdGameName' => 'Jogador', 'riotIdTagline' => 'BR1'];
        }
        unset($player);
        // Import a complete valid response; then simulate historical malformed cache rows for query robustness.
        $original = $players;
        $seen = [];
        foreach ($players as $index => &$player) {
            if (empty($player['puuid']) || isset($seen[$player['puuid']])) {
                $player['puuid'] = 'valid-'.$index;
            }
            $seen[$player['puuid']] = true;
        }
        unset($player);
        foreach ([100, 200] as $team) {
            $missing = 5 - count(array_filter($players, fn ($p) => $p['teamId'] === $team));
            for ($n = 0; $n < $missing; $n++) {
                $slot = count($players) + 1;
                $players[] = ['participantId' => $slot, 'puuid' => 'padding-'.$slot, 'teamId' => $team,
                    'championName' => 'Garen', 'win' => $team === 100, 'kills' => 0, 'deaths' => 0, 'assists' => 0, 'teamPosition' => 'UNKNOWN'];
            }
        }
        app(ImportMatch::class)->execute($account, $id, $this->matchPayload(info: $info + ['participants' => $players]));
        if (($info['queueId'] ?? 420) === 420) {
            $game = RiotMatch::where('match_id', $id)->firstOrFail();
            $game->participants()->where('participant_id', '>', count($original))->delete();
            foreach ($original as $player) {
                $game->participants()->where('participant_id', $player['participantId'])->update(['puuid' => $player['puuid']]);
            }
        }
    }

    private function insights(Conta $account): array
    {
        $base = app(AccountStatistics::class)->base($account->user, 30, $account, 420, Season::current());

        return app(AccountInsights::class)->build($base, includeOpponents: true);
    }

    public function test_separate_leaderboards_group_players_by_identity_and_use_our_results(): void
    {
        $account = $this->account(attributes: ['puuid' => 'owner']);
        $this->game($account, 'BR1_FIRST');
        $this->game($account, 'BR1_SECOND', rivals: [
            ['puuid' => 'enemy-mid', 'teamId' => 200, 'teamPosition' => 'MIDDLE', 'riotIdGameName' => 'NovoNome'],
            ['puuid' => 'enemy-top', 'teamId' => 200, 'teamPosition' => 'TOP'],
        ], owner: ['win' => false]);
        $insight = $this->insights($account);
        $this->assertSame(['enemy-mid'], $insight['laneOpponents']->pluck('puuid')->all());
        $this->assertSame('enemy-mid', $insight['allOpponents']->first()->puuid);
        $this->assertEquals(2, $insight['allOpponents']->first()->games);
        $this->assertEquals(1, $insight['allOpponents']->first()->wins);
        $this->assertCount(3, $insight['allOpponents']);
        $this->assertFalse($insight['allOpponents']->contains('puuid', 'ally'));
        $this->assertFalse($insight['allOpponents']->contains('puuid', 'owner'));
        $this->assertSame('ally', $insight['teammates']->first()->puuid);
    }

    public function test_ambiguous_or_unknown_positions_stay_in_general_list_only(): void
    {
        $account = $this->account(attributes: ['puuid' => 'owner']);
        $this->game($account, 'BR1_AMBIGUOUS', rivals: [
            ['puuid' => 'mid-a', 'teamId' => 200, 'teamPosition' => 'MIDDLE'],
            ['puuid' => 'mid-b', 'teamId' => 200, 'teamPosition' => 'MIDDLE'],
        ]);
        $this->game($account, 'BR1_UNKNOWN', owner: ['teamPosition' => 'UNKNOWN']);
        $insight = $this->insights($account);
        $this->assertCount(0, $insight['laneOpponents']);
        $this->assertCount(4, $insight['allOpponents']);
        $this->game($account, 'BR1_ALLY_AMBIGUOUS', rivals: [
            ['puuid' => 'enemy-top', 'teamId' => 200, 'teamPosition' => 'TOP'],
        ], owner: ['teamPosition' => 'TOP']);
        $this->assertCount(0, $this->insights($account)['laneOpponents']);
    }

    public function test_general_list_deduplicates_a_player_with_two_slots_and_excludes_missing_identity(): void
    {
        $account = $this->account(attributes: ['puuid' => 'owner']);
        $this->game($account, 'BR1_DUPLICATE', rivals: [
            ['puuid' => 'same', 'teamId' => 200, 'teamPosition' => 'TOP'],
            ['puuid' => 'same', 'teamId' => 200, 'teamPosition' => 'JUNGLE'],
            ['puuid' => null, 'teamId' => 200, 'teamPosition' => 'MIDDLE'],
            ['puuid' => '', 'teamId' => 200, 'teamPosition' => 'BOTTOM'],
        ]);
        $list = $this->insights($account)['allOpponents'];
        $this->assertCount(1, $list);
        $this->assertEquals(1, $list->first()->games);
        $this->assertEquals(1, $list->first()->wins);
    }

    public function test_filters_and_owner_scope_apply_to_both_lists(): void
    {
        $account = $this->account(attributes: ['puuid' => 'owner']);
        $this->game($account, 'BR1_OUR');
        $this->game($account, 'BR1_FLEX', info: ['queueId' => 440]);
        $other = $this->account(attributes: ['puuid' => 'other']);
        $this->game($other, 'BR1_OTHER', rivals: [['puuid' => 'private', 'teamId' => 200, 'teamPosition' => 'MIDDLE']]);
        $this->actingAs($account->user)->get('/contas/'.$other->id.'?tab=patterns')->assertNotFound();
        $this->get('/contas/'.$account->id.'?tab=patterns')->assertOk()
            ->assertViewHas('insight', fn ($data) => $data['allOpponents']->count() === 2 && (int) $data['allOpponents']->first()->games === 1);
        foreach (['champion=Lux', 'role=TOP', 'result=loss', 'opponent=Zed'] as $filter) {
            $this->get('/contas/'.$account->id.'?tab=patterns&'.$filter)->assertOk()
                ->assertViewHas('insight', fn ($data) => $data['allOpponents']->isEmpty() && $data['laneOpponents']->isEmpty());
        }
        $account->partidas()->where('queue_id', 420)->update(['played_at' => now()->subDays(40), 'started_at' => now()->subDays(40)]);
        $this->get('/contas/'.$account->id.'?tab=patterns&days=7')->assertOk()
            ->assertViewHas('insight', fn ($data) => $data['allOpponents']->isEmpty());
        $this->get('/contas/'.$account->id.'?tab=patterns&queue=440')->assertSessionHasErrors('queue');
        Season::current()->forceFill(['starts_at' => now()->subDays(2), 'regional_starts' => null])->save();
        $this->get('/contas/'.$account->id.'?tab=patterns&days=0')->assertOk()
            ->assertViewHas('insight', fn ($data) => $data['allOpponents']->isEmpty());
        Http::assertNothingSent();
    }

    public function test_names_are_escaped_and_other_tabs_do_not_build_the_new_lists(): void
    {
        $account = $this->account(attributes: ['puuid' => 'owner']);
        $this->game($account, 'BR1_ESCAPE', rivals: [
            ['puuid' => 'unsafe', 'teamId' => 200, 'teamPosition' => 'MIDDLE', 'riotIdGameName' => '<img onerror=alert(1)>'],
        ]);
        $this->actingAs($account->user)->get('/contas/'.$account->id.'?tab=patterns')->assertOk()
            ->assertSee('Adversários na sua posição')->assertSee('Adversários em todas as posições')
            ->assertSee('&lt;img onerror=alert(1)&gt;#BR1', false)->assertDontSee('<img onerror=alert(1)>', false);
        $this->get('/contas/'.$account->id)->assertOk()
            ->assertViewHas('insight', fn ($data) => $data['allOpponents']->isEmpty() && $data['laneOpponents']->isEmpty());
        Http::assertNothingSent();
    }

    public function test_global_patterns_combine_encounters_from_all_owned_accounts_only(): void
    {
        $user = User::factory()->create();
        $a = $this->account($user, ['puuid' => 'owner-a']);
        $b = $this->account($user, ['puuid' => 'owner-b', 'riot_id' => 'Outra#BR1']);
        $this->game($a, 'BR1_GLOBAL_A');
        $this->game($b, 'BR1_GLOBAL_B', owner: ['win' => false]);
        $foreign = $this->account(attributes: ['puuid' => 'foreign']);
        $this->game($foreign, 'BR1_FOREIGN');
        $this->actingAs($user)->get('/dashboard?tab=patterns')->assertOk()
            ->assertViewHas('insight', fn ($data) => $data['laneOpponents']->count() === 1
                && (int) $data['laneOpponents']->first()->games === 2
                && (int) $data['laneOpponents']->first()->wins === 1
                && $data['allOpponents']->count() === 2
                && (int) $data['teammates']->first()->games === 2);
        Http::assertNothingSent();
    }
}
