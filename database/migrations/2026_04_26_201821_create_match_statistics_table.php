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
       Schema::create('match_statistics', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fixture_id')->constrained('fixtures')->onDelete('cascade');
    $table->string('stat_type'); // 'xg', 'possession', 'shots_on_target', ...
    $table->decimal('home_value', 8, 2)->nullable();
    $table->decimal('away_value', 8, 2)->nullable();
    $table->timestamps();

    $table->unique(['fixture_id', 'stat_type']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_statistics');
    }
};
