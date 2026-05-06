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
      Schema::create('betting_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('value_bet_id')->constrained('value_bets')->onDelete('cascade');
    $table->decimal('stake', 8, 2)->default(1.00);
    $table->decimal('odds_at_bet', 8, 2);
    $table->string('result')->nullable(); // won, lost, void
    $table->decimal('profit_loss', 8, 2)->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('betting_history');
    }
};
