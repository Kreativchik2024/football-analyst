<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('value_bets', function (Blueprint $table) {
            $table->text('explanation')->nullable()->after('edge_percent');
        });
    }

    public function down()
    {
        Schema::table('value_bets', function (Blueprint $table) {
            $table->dropColumn('explanation');
        });
    }
};