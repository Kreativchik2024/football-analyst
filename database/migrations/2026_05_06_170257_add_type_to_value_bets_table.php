<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   // database/migrations/xxxx_add_type_to_value_bets_table.php
public function up()
{
    Schema::table('value_bets', function (Blueprint $table) {
        $table->string('type')->default('prematch')->after('status');
    });
}

public function down()
{
    Schema::table('value_bets', function (Blueprint $table) {
        $table->dropColumn('type');
    });
}

   
};
