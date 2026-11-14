<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What each paid Odds API call was for, so each purpose's monthly allowance can be counted (plan §10.1). Free calls
 * (sports, events) keep NULL. Calls made before this were pre-match odds or settlement scores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sb_odds_api_calls', function (Blueprint $table) {
            $table->string('purpose', 20)->nullable()->after('sport_key');
            $table->index(['purpose', 'created_at']);
        });

        DB::table('sb_odds_api_calls')->where('endpoint', 'odds')->update(['purpose' => 'pre_match']);
        DB::table('sb_odds_api_calls')->where('endpoint', 'scores')->update(['purpose' => 'scores']);
    }

    public function down(): void
    {
        Schema::table('sb_odds_api_calls', function (Blueprint $table) {
            $table->dropIndex(['purpose', 'created_at']);
            $table->dropColumn('purpose');
        });
    }
};
