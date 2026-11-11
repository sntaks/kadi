<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The player activity log (App\Activity\PlayerActivity) is read by GMS per player and by date range.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index(['log_name', 'created_at'], 'activity_log_log_name_created_at_index');
            $table->index(['causer_type', 'causer_id', 'created_at'], 'activity_log_causer_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_log_name_created_at_index');
            $table->dropIndex('activity_log_causer_created_at_index');
        });
    }
};
