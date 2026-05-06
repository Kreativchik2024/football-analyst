<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('match_events', function (Blueprint $table) {
            if (!Schema::hasColumn('match_events', 'fixture_id')) {
                $table->foreignId('fixture_id')->after('id')->constrained('fixtures')->onDelete('cascade');
            }
        });
    }

    public function down()
    {
        Schema::table('match_events', function (Blueprint $table) {
            $table->dropForeign(['fixture_id']);
            $table->dropColumn('fixture_id');
        });
    }
};