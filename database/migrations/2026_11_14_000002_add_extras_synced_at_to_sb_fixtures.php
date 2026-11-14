<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a fixture's per-match extra markets (alternate lines, team goal lines) were last bought from the Odds API's
 * event endpoint (plan §10.6). Each sync window buys them at most once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sb_fixtures', function (Blueprint $table) {
            $table->timestamp('extras_synced_at')->nullable()->after('last_seen_in_feed_at');
        });
    }

    public function down(): void
    {
        Schema::table('sb_fixtures', function (Blueprint $table) {
            $table->dropColumn('extras_synced_at');
        });
    }
};
