<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('teams', function (Blueprint $table) {
            if (!Schema::hasColumn('teams', 'form_goals_scored_avg')) {
                $table->float('form_goals_scored_avg', 8, 2)->nullable()->after('elo_rating');
            }
            if (!Schema::hasColumn('teams', 'form_goals_conceded_avg')) {
                $table->float('form_goals_conceded_avg', 8, 2)->nullable()->after('form_goals_scored_avg');
            }
            if (!Schema::hasColumn('teams', 'form_points_avg')) {
                $table->float('form_points_avg', 8, 2)->nullable()->after('form_goals_conceded_avg');
            }
            if (!Schema::hasColumn('teams', 'form_matches_count')) {
                $table->integer('form_matches_count')->default(0)->after('form_points_avg');
            }
        });
    }

    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'form_goals_scored_avg',
                'form_goals_conceded_avg',
                'form_points_avg',
                'form_matches_count'
            ]);
        });
    }
};