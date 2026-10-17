<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rank_cutoffs', function (Blueprint $table) {
            $table->id();
            $table->string('region', 10);
            $table->unsignedInteger('queue_id')->default(420);
            $table->string('tier', 20);
            $table->unsignedInteger('lp')->nullable();
            $table->string('source_url')->nullable();
            $table->dateTime('source_updated_at')->nullable();
            $table->dateTime('fetched_at')->nullable();
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('last_failed_at')->nullable();
            $table->timestamps();
            $table->unique(['region', 'queue_id', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_cutoffs');
    }
};
