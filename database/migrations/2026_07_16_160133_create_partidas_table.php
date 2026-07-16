<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partidas', function (Blueprint $table) {
            $table->id();
            // Vincula a partida à conta do invocador correspondente
            $table->foreignId('conta_id')->constrained('contas')->onDelete('cascade');
            
            // ID único da partida para evitar duplicados
            $table->string('match_id')->unique();
            
            // Dados de desempenho do jogador
            $table->string('champion_name');
            $table->boolean('win');
            $table->integer('kills');
            $table->integer('deaths');
            $table->integer('assists');
            $table->string('lane'); // TOP, JUNGLE, MIDDLE, BOTTOM, UTILITY
            $table->timestamp('played_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partidas');
    }
};