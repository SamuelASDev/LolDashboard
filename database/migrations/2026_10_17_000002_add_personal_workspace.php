<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas', function (Blueprint $table) {
            $table->timestamp('solo_history_synced_at')->nullable();
            $table->json('solo_history_window')->nullable();
        });
        foreach (['player_notes', 'match_notes', 'filter_favorites', 'personal_goals'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                if ($name === 'player_notes') {
                    $table->string('puuid', 128);
                    $table->string('riot_id');
                    $table->text('body');
                    $table->unique(['user_id', 'puuid']);
                } elseif ($name === 'match_notes') {
                    $table->string('match_id', 128);
                    $table->text('body');
                    $table->unique(['user_id', 'match_id']);
                } elseif ($name === 'filter_favorites') {
                    $table->string('name', 80);
                    $table->json('filters');
                    $table->foreignId('conta_id')->nullable()->constrained('contas')->nullOnDelete();
                } else {
                    $table->string('title', 100);
                    $table->string('metric', 30);
                    $table->decimal('target', 10, 2);
                    $table->date('starts_on');
                    $table->date('ends_on');
                }
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['player_notes', 'match_notes', 'filter_favorites', 'personal_goals'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('contas', fn (Blueprint $table) => $table->dropColumn(['solo_history_synced_at', 'solo_history_window']));
    }
};
