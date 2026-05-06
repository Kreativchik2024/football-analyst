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
       Schema::create('predictions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fixture_id')->constrained('fixtures')->onDelete('cascade');
    $table->string('agent_type'); // 'ml_model', 'api_football', 'market'
    $table->decimal('home_probability', 5, 4);
    $table->decimal('draw_probability', 5, 4);
    $table->decimal('away_probability', 5, 4);
    $table->string('model_version')->nullable();
    $table->json('features_used')->nullable();
    $table->timestamps();

    $table->unique(['fixture_id', 'agent_type', 'model_version'], 'unique_prediction');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
