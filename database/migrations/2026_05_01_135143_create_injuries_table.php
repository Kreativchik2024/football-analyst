<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('injuries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique(); // ID травмы в API
            $table->foreignId('team_id')->constrained('teams');
            $table->foreignId('player_id')->nullable();           // если будет таблица players
            $table->string('player_name');
            $table->string('reason')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('injuries');
    }
};