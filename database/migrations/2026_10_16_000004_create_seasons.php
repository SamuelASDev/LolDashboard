<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('status', 12)->default('draft');
            $table->unsignedTinyInteger('active_slot')->nullable()->unique();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->json('regional_starts')->nullable();
            $table->timestamps();
        });
        DB::table('seasons')->insert([
            'name' => '2026', 'status' => 'active', 'active_slot' => 1,
            'starts_at' => '2026-01-08 15:00:00', 'ends_at' => null,
            'regional_starts' => json_encode([
                'br1' => '2026-01-08 15:00:00', 'na1' => '2026-01-08 20:00:00',
                'euw1' => '2026-01-08 12:00:00', 'eun1' => '2026-01-08 11:00:00',
                'kr' => '2026-01-08 03:00:00', 'jp1' => '2026-01-08 03:00:00',
            ]), 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::table('partidas', function (Blueprint $table) {
            $table->dateTime('started_at')->nullable()->index();
        });
        DB::table('partidas')->orderBy('id')->chunkById(500, function ($matches) {
            $starts = DB::table('riot_matches')->whereIn('id', $matches->pluck('riot_match_id')->filter())->pluck('started_at', 'id');
            foreach ($matches as $match) {
                $start = $match->riot_match_id
                    ? ($starts[$match->riot_match_id] ?? null) : null;
                $start ??= $match->played_at && $match->duration_seconds !== null
                    ? CarbonImmutable::parse($match->played_at)->subSeconds($match->duration_seconds)->toDateTimeString() : null;
                if ($start) {
                    DB::table('partidas')->where('id', $match->id)->update(['started_at' => $start]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            $table->dropIndex(['started_at']);
            $table->dropColumn('started_at');
        });
        Schema::dropIfExists('seasons');
    }
};
