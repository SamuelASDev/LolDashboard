<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas', function (Blueprint $table) {
            $table->string('puuid')->nullable();
            $table->string('identity_key', 64)->nullable()->unique();
            $table->timestamp('rank_synced_at')->nullable();
            $table->timestamp('history_synced_at')->nullable();
            $table->unique(['user_id', 'regiao', 'puuid']);
        });
        Schema::table('partidas', function (Blueprint $table) {
            $table->string('identity_puuid')->nullable();
            $table->unsignedInteger('queue_id')->nullable();
            $table->boolean('is_remake')->default(false);
            $table->index(['conta_id', 'played_at']);
        });
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conta_id')->constrained('contas')->cascadeOnDelete();
            $table->unsignedBigInteger('active_conta_id')->nullable()->unique();
            $table->string('status')->default('queued');
            $table->string('phase')->default('snapshot');
            $table->unsignedInteger('version')->default(0);
            $table->unsignedInteger('cursor')->default(0);
            $table->json('pending_ids')->nullable();
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('retries')->default(0);
            $table->string('message')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('sync_runs');
        Schema::table('partidas', function (Blueprint $table) {
            $table->dropIndex(['conta_id', 'played_at']);
            $table->dropColumn(['identity_puuid', 'queue_id', 'is_remake']);
        });
        Schema::table('contas', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'regiao', 'puuid']);
            $table->dropUnique(['identity_key']);
            $table->dropColumn(['puuid', 'identity_key', 'rank_synced_at', 'history_synced_at']);
        });
    }
};
