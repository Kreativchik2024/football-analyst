<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('fixtures', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('external_id')->unique();
    $table->foreignId('league_id')->constrained('leagues');
    $table->foreignId('home_team_id')->constrained('teams');
    $table->foreignId('away_team_id')->constrained('teams');
    $table->dateTime('starting_at');
    $table->string('status')->default('scheduled'); // scheduled, live, finished, cancelled, postponed
    $table->unsignedTinyInteger('home_score')->nullable();
    $table->unsignedTinyInteger('away_score')->nullable();
    $table->decimal('home_xg', 5, 2)->nullable();
    $table->decimal('away_xg', 5, 2)->nullable();
    $table->json('statistics')->nullable(); // для сводки или deprecated
    $table->timestamps();

    $table->index('status');
    $table->index('starting_at');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
