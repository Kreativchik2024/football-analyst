<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('fixtures', function (Blueprint $table) {
            $table->decimal('home_possession', 5, 2)->nullable()->after('away_xg');
            $table->decimal('away_possession', 5, 2)->nullable()->after('home_possession');
        });
    }

    public function down()
    {
        Schema::table('fixtures', function (Blueprint $table) {
            $table->dropColumn(['home_possession', 'away_possession']);
        });
    }
};