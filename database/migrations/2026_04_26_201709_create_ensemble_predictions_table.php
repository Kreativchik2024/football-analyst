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
       Schema::create('ensemble_predictions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fixture_id')->constrained('fixtures')->onDelete('cascade');
    $table->decimal('home_probability', 5, 4);
    $table->decimal('draw_probability', 5, 4);
    $table->decimal('away_probability', 5, 4);
    $table->json('top3_outcomes')->nullable(); // [{outcome, probability}, ...]
    $table->timestamps();

    $table->unique('fixture_id');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ensemble_predictions');
    }
};
