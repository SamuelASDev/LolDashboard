<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            // 1. Remove a trava unique antiga que estava isolada no match_id
            // O nome do índice padrão do Laravel costuma ser 'partidas_match_id_unique'
            $table->dropUnique('partidas_match_id_unique');

            // 2. Cria a nova trava combinada de segurança máxima
            // Agora, o mesmo match_id pode repetir, contanto que seja para CONTAS diferentes!
            $table->unique(['match_id', 'conta_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Abort before modifying indexes: shared matches are valid under the new schema.
        if (DB::table('partidas')->select('match_id')->groupBy('match_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Rollback interrompido: existem partidas compartilhadas entre contas. Nenhum dado foi apagado.');
        }
        Schema::table('partidas', function (Blueprint $table) {
            // Reverte a alteração caso precise dar um rollback
            $table->dropUnique(['match_id', 'conta_id']);
            $table->unique('match_id');
        });
    }
};
