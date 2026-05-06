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
      Schema::create('user_predictions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('fixture_id')->constrained('fixtures')->onDelete('cascade');
    $table->string('bet_type'); // home, draw, away
    $table->decimal('stake', 10, 2);
    $table->decimal('odds', 8, 2);
    $table->string('status')->default('pending'); // pending, won, lost, void
    $table->decimal('profit', 10, 2)->nullable();
    $table->timestamp('settled_at')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_predictions');
    }
};
