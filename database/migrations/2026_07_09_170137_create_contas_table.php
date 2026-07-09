<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas', function (Blueprint $table) {
            $table->id();
            
            // Relacionamento com o usuário logado
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('nick');
            $table->string('riot_id');
            $table->string('email')->nullable(); // Caso queira guardar o email de aviso/contato da conta
            $table->string('regiao')->default('br1');

            // Campos de Rank que a API da Riot vai preencher
            $table->string('elo')->nullable();
            $table->integer('pdl')->nullable();
            $table->integer('wins')->nullable();
            $table->integer('losses')->nullable();
            $table->float('winrate')->nullable();
            $table->integer('cutoff_lp')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas');
    }
};