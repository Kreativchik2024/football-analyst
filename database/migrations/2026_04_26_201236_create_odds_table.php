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
       Schema::create('odds', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fixture_id')->constrained('fixtures')->onDelete('cascade');
    $table->foreignId('bookmaker_id')->constrained('bookmakers');
    $table->string('market')->default('1x2');
    $table->string('outcome'); // home, draw, away
    $table->decimal('value', 8, 2);
    $table->timestamp('fetched_at')->useCurrent();
    $table->timestamps();

    $table->unique(['fixture_id', 'bookmaker_id', 'market', 'outcome'], 'unique_odd');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odds');
    }
};
