<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riot_matches', function (Blueprint $table) {
            $table->id();
            $table->string('match_id')->unique();
            $table->string('routing', 20);
            $table->unsignedInteger('queue_id');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->dateTime('played_at');
            $table->string('game_version', 40)->nullable();
            $table->json('teams')->nullable();
            $table->json('payload');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->timestamps();
        });
        Schema::create('match_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('riot_match_id')->constrained('riot_matches')->cascadeOnDelete();
            $table->unsignedSmallInteger('participant_id');
            $table->string('puuid')->nullable()->index();
            $table->string('riot_id');
            $table->unsignedSmallInteger('team_id');
            $table->unsignedInteger('champion_id')->nullable();
            $table->string('champion_name');
            $table->string('position', 20);
            $table->boolean('win');
            $table->unsignedSmallInteger('champion_level')->nullable();
            $table->unsignedInteger('kills');
            $table->unsignedInteger('deaths');
            $table->unsignedInteger('assists');
            $table->json('items');
            $table->json('spells');
            $table->json('runes');
            $table->json('stats');
            $table->unique(['riot_match_id', 'participant_id']);
        });
        Schema::table('partidas', function (Blueprint $table) {
            $table->foreignId('riot_match_id')->nullable()->constrained('riot_matches')->nullOnDelete();
            $table->unsignedInteger('champion_id')->nullable();
            $table->unsignedSmallInteger('team_id')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('cs')->nullable();
            $table->unsignedInteger('gold')->nullable();
            $table->unsignedInteger('damage')->nullable();
            $table->unsignedInteger('damage_taken')->nullable();
            $table->unsignedInteger('vision_score')->nullable();
            $table->unsignedInteger('wards_placed')->nullable();
            $table->unsignedInteger('wards_killed')->nullable();
            $table->unsignedInteger('control_wards')->nullable();
            $table->float('kill_participation')->nullable();
            $table->unsignedInteger('opponent_champion_id')->nullable();
            $table->string('opponent_champion_name')->nullable();
            $table->boolean('ended_in_surrender')->nullable();
            $table->unsignedSmallInteger('penta_kills')->nullable();
            $table->unsignedInteger('healing')->nullable();
            $table->json('items')->nullable();
            $table->json('spells')->nullable();
            $table->json('runes')->nullable();
            $table->timestamp('details_synced_at')->nullable();
            $table->index(['conta_id', 'queue_id', 'played_at'], 'partidas_account_queue_date');
        });
        Schema::table('sync_runs', function (Blueprint $table) {
            // Existing runs retain their previous full-history semantics.
            $table->string('mode', 20)->default('full');
            $table->unsignedInteger('queue_id')->default(420);
            $table->boolean('stop_after_page')->default(false);
            $table->json('context')->nullable();
        });
        Schema::create('rank_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->unsignedInteger('queue_id')->default(420);
            $table->string('tier', 20);
            $table->string('division', 5)->nullable();
            $table->unsignedInteger('lp');
            $table->unsignedInteger('wins');
            $table->unsignedInteger('losses');
            $table->dateTime('observed_at');
            $table->index(['conta_id', 'queue_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_snapshots');
        Schema::table('sync_runs', fn (Blueprint $table) => $table->dropColumn(['mode', 'queue_id', 'stop_after_page', 'context']));
        Schema::table('partidas', function (Blueprint $table) {
            $table->dropForeign(['riot_match_id']);
            $table->dropIndex('partidas_account_queue_date');
            $table->dropColumn(['riot_match_id', 'champion_id', 'team_id', 'duration_seconds', 'cs', 'gold', 'damage',
                'damage_taken', 'vision_score', 'wards_placed', 'wards_killed', 'control_wards', 'kill_participation',
                'opponent_champion_id', 'opponent_champion_name', 'ended_in_surrender', 'penta_kills', 'healing',
                'items', 'spells', 'runes', 'details_synced_at']);
        });
        Schema::dropIfExists('match_participants');
        Schema::dropIfExists('riot_matches');
    }
};
