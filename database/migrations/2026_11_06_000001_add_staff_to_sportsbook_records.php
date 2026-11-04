<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10: GMS staff (not users of this site) override prices and record results through the back-office API.
 * Who did it is kept beside the old user columns as "Name (GMS #id)".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sb_selections', function (Blueprint $table) {
            $table->string('override_by_staff', 150)->nullable()->after('override_by_admin_id');
        });

        Schema::table('sb_fixture_results', function (Blueprint $table) {
            $table->string('recorded_by_staff', 150)->nullable()->after('recorded_by_admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('sb_selections', fn (Blueprint $table) => $table->dropColumn('override_by_staff'));
        Schema::table('sb_fixture_results', fn (Blueprint $table) => $table->dropColumn('recorded_by_staff'));
    }
};
