<?php

namespace Tests\Feature;

use App\Actions\Sync\ImportMatch;
use App\Models\Season;
use App\Models\User;
use App\Queries\AccountRankHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class PersonalWorkspaceTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    private function encounter(): array
    {
        Http::preventStrayRequests();
        $account = $this->account(attributes: ['puuid' => 'owner']);
        $payload = $this->matchPayload('owner');
        $payload['info']['participants'][1]['riotIdGameName'] = 'Aliado';
        $payload['info']['participants'][5]['riotIdGameName'] = 'Inimigo';
        app(ImportMatch::class)->execute($account, 'BR1_NOTE', $payload);

        return [$account, $account->partidas()->first()];
    }

    public function test_search_shows_encounters_and_private_note_and_matches(): void
    {
        [$account, $match] = $this->encounter();
        $this->actingAs($account->user)->put('/jogadores/fixture-2/anotacao', ['body' => 'Bom suporte <script>alert(1)</script>'])->assertSessionHasNoErrors();
        $this->get('/jogadores?q=Aliado')->assertOk()->assertSee('Com anotação')->assertSee('Bom suporte')->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/jogadores/fixture-2?relation=ally')->assertOk()->assertViewHas('matches', fn ($p) => $p->total() === 1);
        $this->get('/jogadores/fixture-2?relation=enemy')->assertOk()->assertViewHas('matches', fn ($p) => $p->total() === 0);
        $this->put('/partidas/'.$match->id.'/anotacao', ['body' => 'Rever luta do dragão'])->assertSessionHasNoErrors();
        $this->get('/dashboard?tab=history')->assertOk()->assertSee('Rever luta do dragão')->assertSee('Com anotacao');
        Http::assertNothingSent();
    }

    public function test_foreign_player_match_and_notes_cannot_be_read_or_written(): void
    {
        [$account, $match] = $this->encounter();
        $this->actingAs($account->user)->put('/jogadores/fixture-2/anotacao', ['body' => 'Segredo'])->assertSessionHasNoErrors();
        $other = User::factory()->create();
        $this->actingAs($other)->get('/jogadores/fixture-2')->assertNotFound();
        $this->put('/jogadores/fixture-2/anotacao', ['body' => 'Ataque'])->assertNotFound();
        $this->put('/partidas/'.$match->id.'/anotacao', ['body' => 'Ataque'])->assertNotFound();
        $this->get('/jogadores?q=Aliado')->assertOk()->assertDontSee('Segredo')->assertViewHas('players', fn ($p) => $p->total() === 0);
        $this->get('/caderno')->assertOk()->assertDontSee('Segredo');
    }

    public function test_favorites_keep_validated_internal_context_and_are_owned(): void
    {
        [$account] = $this->encounter();
        $this->actingAs($account->user)->post('/caderno/favoritos', ['name' => 'Meu Mid', 'conta_id' => $account->id,
            'filters' => ['tab' => 'history', 'days' => 30, 'role' => 'MIDDLE', 'redirect' => 'https://attacker.invalid']])->assertRedirect('/caderno');
        $row = DB::table('filter_favorites')->first();
        $this->assertStringNotContainsString('attacker', $row->filters);
        $this->get('/caderno')->assertOk()->assertSee('Meu Mid');
        $this->post('/caderno/favoritos', ['name' => 'Flex', 'filters' => ['queue' => 440]])->assertSessionHasErrors('queue');
        $this->actingAs(User::factory()->create())->delete('/caderno/favoritos/'.$row->id)->assertNotFound();
        $this->post('/caderno/favoritos', ['name' => 'Alheio', 'conta_id' => $account->id, 'filters' => ['tab' => 'history']])->assertNotFound();
    }

    public function test_goals_aggregate_owned_confirmed_solo_games_and_validate_dates(): void
    {
        [$account] = $this->encounter();
        $second = $this->account($account->user, ['puuid' => 'second']);
        $this->matchRecord($second);
        $this->matchRecord($second, ['queue_id' => 440]);
        $this->matchRecord($second, ['identity_puuid' => null]);
        $this->matchRecord($second, ['is_remake' => true]);
        $this->matchRecord($this->account(attributes: ['puuid' => 'foreign']));
        $data = ['title' => 'Duas vitórias', 'metric' => 'wins', 'target' => 2, 'starts_on' => now()->toDateString(), 'ends_on' => now()->addDay()->toDateString()];
        $this->actingAs($account->user)->post('/caderno/metas', $data)->assertSessionHasNoErrors();
        $this->get('/caderno')->assertOk()->assertViewHas('goals', fn ($goals) => $goals->first()->value == 2)->assertSee('Meta atingida');
        $this->post('/caderno/metas', array_merge($data, ['starts_on' => 'invalid']))->assertSessionHasErrors('starts_on');
        $this->post('/caderno/metas', array_merge($data, ['metric' => 'winrate', 'target' => 101]))->assertSessionHasErrors('target');
        $id = DB::table('personal_goals')->value('id');
        $this->actingAs(User::factory()->create())->delete('/caderno/metas/'.$id)->assertNotFound();
    }

    public function test_search_treats_wildcards_literally_and_note_can_be_removed(): void
    {
        [$account] = $this->encounter();
        $this->actingAs($account->user)->get('/jogadores?q=%25')->assertOk()->assertViewHas('players', fn ($p) => $p->total() === 0);
        $this->get('/jogadores?q=_')->assertOk()->assertViewHas('players', fn ($p) => $p->total() === 0);
        $this->put('/jogadores/fixture-2/anotacao', ['body' => 'Teste'])->assertSessionHasNoErrors();
        $this->put('/jogadores/fixture-2/anotacao', ['body' => ''])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('player_notes', 0);
        $this->put('/jogadores/unknown/anotacao', ['body' => 'Teste'])->assertNotFound();
    }

    public function test_champions_does_not_load_scoreboards_or_full_match_cursor(): void
    {
        [$account] = $this->encounter();
        DB::enableQueryLog();
        $this->actingAs($account->user)->get('/dashboard?tab=champions')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        $this->assertStringNotContainsString('select * from "match_participants"', $queries);
        $this->assertFalse(collect(DB::getQueryLog())->contains(fn ($q) => str_contains($q['query'], 'select "partidas".*') && ! str_contains($q['query'], 'limit')));
        DB::disableQueryLog();
    }

    public function test_insight_cache_is_private_and_invalidates_after_match_write(): void
    {
        [$account] = $this->encounter();
        $this->actingAs($account->user)->get('/dashboard?tab=patterns')->assertOk();
        DB::enableQueryLog();
        $this->get('/dashboard?tab=patterns')->assertOk();
        $this->assertFalse(collect(DB::getQueryLog())->contains(fn ($q) => str_contains($q['query'], 'join "match_participants" as "rivals"')));
        DB::disableQueryLog();
        $this->matchRecord($account, ['kills' => 30]);
        $this->get('/dashboard?tab=patterns')->assertOk()->assertViewHas('insight', fn ($i) => $i['total'] === 2);
        $this->actingAs(User::factory()->create())->get('/dashboard?tab=patterns')->assertOk()->assertViewHas('insight', fn ($i) => $i['total'] === 0);
    }

    public function test_rank_history_transfers_only_last_sixty_snapshots_per_account(): void
    {
        [$account] = $this->encounter();
        foreach (range(1, 75) as $n) {
            $account->rankSnapshots()->create(['queue_id' => 420, 'tier' => 'GOLD', 'division' => 'I', 'lp' => $n,
                'wins' => 1, 'losses' => 0, 'observed_at' => now()->subMinutes(76 - $n)]);
        }
        $charts = app(AccountRankHistory::class)->accounts(collect([$account]), Season::current(), 30);
        $this->assertCount(60, $charts->first()['points']);
    }
}
