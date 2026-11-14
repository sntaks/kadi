<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The live score of a match in play, from the scores feed (plan §4.1). Shown while the match is on and used to
 * suspend markets when it changes; the result used for settlement stays in sb_fixture_results.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sb_fixtures', function (Blueprint $table) {
            $table->unsignedSmallInteger('home_score')->nullable()->after('extras_synced_at');
            $table->unsignedSmallInteger('away_score')->nullable()->after('home_score');
            $table->timestamp('score_updated_at')->nullable()->after('away_score');
            $table->timestamp('score_changed_at')->nullable()->after('score_updated_at');
            $table->timestamp('feed_completed_at')->nullable()->after('score_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('sb_fixtures', function (Blueprint $table) {
            $table->dropColumn(['home_score', 'away_score', 'score_updated_at', 'score_changed_at', 'feed_completed_at']);
        });
    }
};
