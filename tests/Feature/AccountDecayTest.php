<?php

namespace Tests\Feature;

use App\Actions\Accounts\SaveDecayReference;
use App\Actions\Sync\AdvanceSync;
use App\Enums\RiotRegion;
use App\Exceptions\RiotException;
use App\Models\Conta;
use App\Models\Season;
use App\Models\User;
use App\Queries\AccountDecay;
use App\Support\HistoryWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class AccountDecayTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-17 15:00:00', 'UTC'));
    }

    private function apex(array $attributes = []): Conta
    {
        return $this->account(attributes: $attributes + ['puuid' => 'player', 'elo' => 'MASTER I',
            'rank_synced_at' => now(), 'history_synced_at' => now()]);
    }

    private function projection(Conta $account): ?array
    {
        return app(AccountDecay::class)->accounts(collect([$account->fresh()]))->get($account->id);
    }

    public function test_only_confirmed_apex_solo_rank_is_monitored(): void
    {
        foreach (['MASTER I', 'GRANDMASTER I', 'CHALLENGER I'] as $rank) {
            $this->assertNotNull($this->projection($this->apex(['elo' => $rank])));
        }
        foreach ([['elo' => 'DIAMOND I'], ['elo' => 'EMERALD I'], ['elo' => 'UNRANKED'],
            ['rank_synced_at' => null], ['puuid' => null]] as $attributes) {
            $this->assertNull($this->projection($this->apex($attributes)));
        }
    }

    public function test_manual_bank_counts_down_and_warns_before_expiry_without_changing_lp(): void
    {
        $account = $this->apex(['pdl' => 500]);
        app(SaveDecayReference::class)->execute($account->user, $account->id, 3);
        $this->assertSame(3 * 86400, $this->projection($account)['lower']);
        $this->assertSame('protected', $this->projection($account)['status']);
        $this->travel(25)->hours();
        $this->assertSame('warning', $this->projection($account)['status']);
        $this->assertSame('1 d 23 h', $this->projection($account)['remaining']);
        $this->travel(48)->hours();
        $this->assertSame('possible_decay', $this->projection($account)['status']);
        $this->assertSame(500, $account->fresh()->pdl);
    }

    public function test_one_match_adds_only_one_day_and_ignores_other_queues_legacy_remakes_and_future_games(): void
    {
        $account = $this->apex();
        app(SaveDecayReference::class)->execute($account->user, $account->id, 2);
        $this->travel(1)->hours();
        $this->matchRecord($account, ['details_synced_at' => now()]);
        foreach ([['queue_id' => 440], ['identity_puuid' => 'other'], ['identity_puuid' => null],
            ['is_remake' => true], ['details_synced_at' => null], ['played_at' => now()->addDay()]] as $attributes) {
            $this->matchRecord($account, $attributes + ['details_synced_at' => now()]);
        }
        $other = $this->apex();
        $this->matchRecord($other, ['details_synced_at' => now()]);
        $decay = $this->projection($account);
        $this->assertSame(3 * 86400 - 3600, $decay['lower']);
        $this->assertSame(1, $decay['games']);
        $this->assertTrue($decay['manual']);
    }

    public function test_reserve_caps_at_fourteen_and_reimport_does_not_credit_a_match_twice(): void
    {
        $account = $this->apex();
        app(SaveDecayReference::class)->execute($account->user, $account->id, 13);
        $this->travel(1)->hours();
        for ($i = 0; $i < 20; $i++) {
            $this->matchRecord($account, ['details_synced_at' => now()]);
        }
        $this->assertSame(14 * 86400, $this->projection($account)['lower']);
        $account->partidas()->first()->update(['details_synced_at' => now()->addMinute()]);
        $this->assertSame(14 * 86400, $this->projection($account)['lower']);
        $this->assertDatabaseCount('partidas', 20);
    }

    public function test_playing_after_exhaustion_adds_one_day_without_accumulating_negative_debt(): void
    {
        $account = $this->apex();
        app(SaveDecayReference::class)->execute($account->user, $account->id, 0);
        $this->travel(3)->days();
        $this->matchRecord($account, ['details_synced_at' => now()]);
        $this->assertSame(86400, $this->projection($account)['lower']);
    }

    public function test_automatic_projection_exposes_unknown_initial_bank_instead_of_resetting_fourteen_days(): void
    {
        $account = $this->apex();
        $this->matchRecord($account, ['played_at' => now()->subHours(2), 'details_synced_at' => now()]);
        $decay = $this->projection($account);
        $this->assertFalse($decay['manual']);
        $this->assertSame(22 * 3600, $decay['lower']);
        $this->assertSame(86400, $decay['upper']);
        // Um jogo recente não garante 14 dias: o saldo anterior pode ter sido esgotado.
        $this->assertLessThan(14 * 86400, $decay['upper']);
    }

    public function test_current_season_start_keeps_an_unknown_range_and_invalidates_an_old_reference(): void
    {
        $account = $this->apex();
        app(SaveDecayReference::class)->execute($account->user, $account->id, 7);
        Season::current()->forceFill(['starts_at' => now()->subHours(12), 'regional_starts' => null])->save();
        $reference = $account->fresh()->decay_reference;
        $reference['season_id'] = 999;
        $account->forceFill(['decay_reference' => $reference])->save();
        $decay = $this->projection($account);
        $this->assertFalse($decay['manual']);
        $this->assertSame(0, $decay['lower']);
        $this->assertSame((int) (13.5 * 86400), $decay['upper']);
        $this->assertSame('uncertain', $decay['status']);
    }

    public function test_owner_can_set_and_remove_reference_but_another_user_cannot_access_it(): void
    {
        $account = $this->apex();
        $this->actingAs(User::factory()->create())->put('/contas/'.$account->id.'/decay', ['decay_days' => 5])->assertNotFound();
        $this->delete('/contas/'.$account->id.'/decay')->assertNotFound();
        $this->actingAs($account->user)->put('/contas/'.$account->id.'/decay', ['decay_days' => 5, 'puuid' => 'injected'])->assertRedirect('/contas/'.$account->id);
        $this->assertSame(5, $account->fresh()->decay_reference['days']);
        $this->assertSame('player', $account->fresh()->decay_reference['puuid']);
        $this->delete('/contas/'.$account->id.'/decay')->assertRedirect();
        $this->assertNull($account->fresh()->decay_reference);
    }

    public function test_server_rejects_invalid_reserves_and_non_apex_accounts(): void
    {
        $account = $this->apex();
        $this->actingAs($account->user);
        foreach ([-1, 15, 1.5, 'bad', null] as $days) {
            $this->put('/contas/'.$account->id.'/decay', ['decay_days' => $days])->assertSessionHasErrors('decay_days');
        }
        $account->forceFill(['elo' => 'DIAMOND I'])->save();
        $this->put('/contas/'.$account->id.'/decay', ['decay_days' => 5])->assertSessionHasErrors('decay_days');
        $this->assertNull($account->fresh()->decay_reference);
    }

    public function test_views_render_prediction_without_network_and_independently_of_filters(): void
    {
        $account = $this->apex();
        app(SaveDecayReference::class)->execute($account->user, $account->id, 1);
        $this->actingAs($account->user)->get('/dashboard?days=7')->assertOk()->assertSee('Decay · Solo/Duo Mestre+')->assertSee('1 d 0 h');
        $this->get('/contas/'.$account->id.'?queue=420&days=7&champion=Lux')->assertOk()->assertSee('1 d 0 h')->assertSee('Salvar reserva atual');
        $this->travel(2)->days();
        $this->get('/dashboard')->assertOk()->assertSee('Possível decay em andamento')->assertSee('Perda real de LP não confirmada')->assertSee('Dados incompletos ou desatualizados');
        Http::assertNothingSent();
    }

    public function test_successful_non_apex_snapshot_discards_reference_but_external_failure_preserves_it(): void
    {
        $account = $this->apex();
        app(SaveDecayReference::class)->execute($account->user, $account->id, 8);
        $run = $account->syncRuns()->create(['active_conta_id' => $account->id,
            'context' => ['history_window' => HistoryWindow::forRegion(RiotRegion::BR)]]);
        Http::fake(['*/riot/account/*' => Http::response(['puuid' => 'player']), '*/lol/league/*' => Http::sequence()->push([], 503)->push([])]);
        try {
            app(AdvanceSync::class)->execute($run);
        } catch (RiotException) {
            $this->assertSame(8, $account->fresh()->decay_reference['days']);
        }
        app(AdvanceSync::class)->execute($run);
        $this->assertNull($account->fresh()->decay_reference);
        $this->assertSame('UNRANKED', $account->fresh()->elo);
    }
}
