<?php

namespace Tests\Feature;

use App\Models\Conta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    public function test_guests_and_unverified_users_cannot_access_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->actingAs(User::factory()->unverified()->create())->get('/dashboard')->assertRedirect('/verify-email');
    }

    public function test_dashboard_and_all_account_pages_render_without_network(): void
    {
        Http::fake();
        $conta = $this->account(attributes: ['puuid' => 'p']);
        $this->matchRecord($conta);
        $this->actingAs($conta->user)->get('/dashboard')->assertOk()->assertSee('Ahri');
        $this->get('/contas/'.$conta->id)->assertOk()->assertSee('Partidas confirmadas');
        $this->get('/contas/'.$conta->id.'/edit')->assertOk();
        $this->get('/contas/create')->assertOk();
        Http::assertNothingSent();
    }

    public function test_empty_dashboard_renders(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertSee('Seu painel começa');
    }

    public function test_account_ids_cannot_cross_the_owner_boundary(): void
    {
        $other = $this->account();
        $this->actingAs(User::factory()->create());
        foreach (['/contas/'.$other->id, '/contas/'.$other->id.'/edit', '/contas/'.$other->id.'/sync'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->put('/contas/'.$other->id, ['nick' => 'x', 'riot_id' => 'Nome#BR1', 'regiao' => 'br1'])->assertNotFound();
        $this->delete('/contas/'.$other->id)->assertNotFound();
        $this->post('/contas/'.$other->id.'/sync')->assertNotFound();
        $this->delete('/contas/'.$other->id.'/sync')->assertNotFound();
    }

    public function test_region_and_riot_id_are_validated_before_any_http_call(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create())->post('/contas', [
            'nick' => 'Nome', 'riot_id' => 'Nome#BR1', 'regiao' => 'attacker.invalid/path',
        ])->assertSessionHasErrors('regiao');
        $this->post('/contas', ['nick' => 'Nome', 'riot_id' => '<script>bad</script>#BR1', 'regiao' => 'br1'])
            ->assertSessionHasErrors('riot_id');
        $this->post('/contas', ['nick' => str_repeat('a', 81), 'riot_id' => 'Nome#BR1', 'regiao' => 'br1'])
            ->assertSessionHasErrors('nick');
        $this->assertDatabaseCount('contas', 0);
        Http::assertNothingSent();
    }

    public function test_duplicates_are_rejected_and_privileged_fields_are_ignored(): void
    {
        $user = User::factory()->create();
        $data = ['nick' => 'Principal', 'riot_id' => 'Nome#BR1', 'regiao' => 'br1'];
        $this->actingAs($user)->post('/contas', $data + ['user_id' => 12345, 'puuid' => 'fake', 'pdl' => 9999])->assertRedirect('/dashboard');
        $conta = Conta::first();
        $this->assertSame($user->id, $conta->user_id);
        $this->assertNull($conta->puuid);
        $this->assertNull($conta->pdl);
        $this->post('/contas', array_merge($data, ['riot_id' => 'nome#br1']))->assertSessionHasErrors('riot_id');
        $this->assertDatabaseCount('contas', 1);
    }

    public function test_confirmed_identity_cannot_be_switched_but_nickname_can_change(): void
    {
        $conta = $this->account(attributes: ['puuid' => 'p']);
        $this->matchRecord($conta);
        $this->actingAs($conta->user)->put('/contas/'.$conta->id, [
            'nick' => 'Novo', 'riot_id' => 'Outra#BR1', 'regiao' => 'br1',
        ])->assertSessionHasErrors('riot_id');
        $this->put('/contas/'.$conta->id, ['nick' => 'Novo', 'riot_id' => $conta->riot_id, 'regiao' => 'br1'])->assertRedirect('/dashboard');
        $this->assertSame('Novo', $conta->fresh()->nick);
        $this->assertDatabaseCount('partidas', 1);
    }

    public function test_stats_exclude_other_users_legacy_wrong_identity_and_outside_period(): void
    {
        $conta = $this->account(attributes: ['puuid' => 'p']);
        $this->matchRecord($conta);
        $this->matchRecord($conta, ['identity_puuid' => null]);
        $this->matchRecord($conta, ['identity_puuid' => 'wrong']);
        $this->matchRecord($conta, ['is_remake' => true]);
        $this->matchRecord($conta, ['played_at' => now()->subDays(40)]);
        $this->matchRecord($this->account(attributes: ['puuid' => 'other']));
        $this->actingAs($conta->user)->get('/dashboard')->assertViewHas('summary', fn ($s) => $s['games'] === 1 && $s['kda'] === '6.00');
        $this->get('/dashboard?days=0')->assertViewHas('summary', fn ($s) => $s['games'] === 2);
        $this->get('/dashboard?days=999')->assertSessionHasErrors('days');
    }

    public function test_sync_is_queued_deduplicated_and_scoped(): void
    {
        Queue::fake();
        $conta = $this->account();
        $other = $this->account();
        $this->actingAs($conta->user)->post('/contas/'.$conta->id.'/sync')->assertRedirect('/contas/'.$conta->id);
        $this->post('/contas/'.$conta->id.'/sync')->assertRedirect();
        $this->assertDatabaseCount('sync_runs', 1);
        $this->post('/contas/sync-all')->assertRedirect('/dashboard');
        $this->assertDatabaseCount('sync_runs', 1);
        $this->assertDatabaseMissing('sync_runs', ['conta_id' => $other->id]);
        $this->get('/contas/'.$conta->id.'/sync')->assertOk()->assertJsonPath('run.active', true);
        $this->delete('/contas/'.$conta->id.'/sync')->assertRedirect();
        $this->get('/contas/'.$conta->id.'/sync')->assertJsonPath('run.status', 'cancelled');
    }

    public function test_active_sync_protects_edit_and_delete(): void
    {
        Queue::fake();
        $conta = $this->account();
        $this->actingAs($conta->user)->post('/contas/'.$conta->id.'/sync');
        $this->put('/contas/'.$conta->id, ['nick' => 'Novo', 'riot_id' => 'Nome#BR1', 'regiao' => 'br1'])->assertSessionHasErrors('riot_id');
        $this->delete('/contas/'.$conta->id)->assertStatus(409);
    }

    public function test_legacy_html_is_escaped_in_pages(): void
    {
        $conta = $this->account(attributes: ['riot_id' => '<script>alert(1)</script>#BR1']);
        $this->actingAs($conta->user)->get('/dashboard')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_detail_history_is_paginated(): void
    {
        $conta = $this->account(attributes: ['puuid' => 'p']);
        for ($i = 0; $i < 25; $i++) {
            $this->matchRecord($conta);
        }
        $this->actingAs($conta->user)->get('/contas/'.$conta->id)->assertOk()
            ->assertViewHas('partidas', fn ($p) => $p->count() === 20 && $p->total() === 25);
    }
}
