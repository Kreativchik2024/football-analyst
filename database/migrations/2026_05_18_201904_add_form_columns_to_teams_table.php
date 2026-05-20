<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('teams', function (Blueprint $table) {
        if (!Schema::hasColumn('teams', 'form_goals_scored_avg')) {
            $table->float('form_goals_scored_avg', 5, 2)->nullable();
        }
        if (!Schema::hasColumn('teams', 'form_goals_conceded_avg')) {
            $table->float('form_goals_conceded_avg', 5, 2)->nullable();
        }
        if (!Schema::hasColumn('teams', 'form_points_avg')) {
            $table->float('form_points_avg', 5, 2)->nullable();
        }
        if (!Schema::hasColumn('teams', 'form_matches_count')) {
            $table->integer('form_matches_count')->default(0);
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
