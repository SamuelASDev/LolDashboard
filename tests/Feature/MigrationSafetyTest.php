<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsAccounts;
use Tests\TestCase;

class MigrationSafetyTest extends TestCase
{
    use BuildsAccounts, RefreshDatabase;

    public function test_additive_upgrade_preserves_existing_accounts_and_partidas(): void
    {
        // RefreshDatabase guarantees this connection is the isolated SQLite test database.
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Artisan::call('migrate:rollback', ['--step' => 6, '--force' => true]);
        $user = User::factory()->create();
        $id = DB::table('contas')->insertGetId(['user_id' => $user->id, 'nick' => 'Legado',
            'riot_id' => 'Antigo#BR1', 'regiao' => 'br1', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('partidas')->insert(['conta_id' => $id, 'match_id' => 'BR1_LEGACY',
            'champion_name' => 'Ahri', 'win' => true, 'kills' => 1, 'deaths' => 0,
            'assists' => 5, 'lane' => 'MIDDLE', 'played_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        Artisan::call('migrate', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('sync_runs'));
        $this->assertTrue(Schema::hasTable('riot_matches'));
        $this->assertTrue(Schema::hasTable('rank_cutoffs'));
        $this->assertTrue(Schema::hasColumn('partidas', 'details_synced_at'));
        $this->assertTrue(Schema::hasColumn('contas', 'decay_reference'));
        $this->assertDatabaseHas('contas', ['id' => $id, 'riot_id' => 'Antigo#BR1', 'puuid' => null]);
        $this->assertDatabaseHas('partidas', ['match_id' => 'BR1_LEGACY', 'identity_puuid' => null]);
    }

    public function test_legacy_rollback_refuses_shared_ids_before_modifying_constraints(): void
    {
        $a = $this->account(attributes: ['puuid' => 'a']);
        $b = $this->account(attributes: ['puuid' => 'b']);
        $this->matchRecord($a, ['match_id' => 'BR1_SHARED']);
        $this->matchRecord($b, ['match_id' => 'BR1_SHARED']);
        $migration = require database_path('migrations/2026_07_17_032127_alterar_unique_em_partidas_table.php');
        try {
            $migration->down();
            $this->fail('Rollback must refuse a lossy unique constraint.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Rollback interrompido', $error->getMessage());
            $this->assertDatabaseCount('partidas', 2);
        }
    }
}
