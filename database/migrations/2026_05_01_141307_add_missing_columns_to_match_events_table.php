<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('match_events', function (Blueprint $table) {
            // Добавляем только те колонки, которых ещё нет
            if (!Schema::hasColumn('match_events', 'elapsed')) {
                $table->integer('elapsed')->nullable()->after('fixture_id');
            }
            if (!Schema::hasColumn('match_events', 'event_type')) {
                $table->string('event_type')->nullable()->after('elapsed');
            }
            if (!Schema::hasColumn('match_events', 'player_name')) {
                $table->string('player_name')->nullable()->after('event_type');
            }
            if (!Schema::hasColumn('match_events', 'detail')) {
                $table->string('detail')->nullable()->after('player_name');
            }
            if (!Schema::hasColumn('match_events', 'assist_name')) {
                $table->string('assist_name')->nullable()->after('detail');
            }
            if (!Schema::hasColumn('match_events', 'team_type')) {
                $table->string('team_type')->nullable()->after('assist_name');
            }
        });
    }

    public function down()
    {
        Schema::table('match_events', function (Blueprint $table) {
            $table->dropColumn([
                'elapsed',
                'event_type',
                'player_name',
                'detail',
                'assist_name',
                'team_type'
            ]);
        });
    }
};