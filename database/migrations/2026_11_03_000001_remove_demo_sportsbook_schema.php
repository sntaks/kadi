<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Removes what only the demo sportsbook used: its Odds API quota table and the
 * permission for its admin page. The merged sportsbook brings its own table,
 * and the admin panel will define fresh permissions when it is reviewed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('odds_api_quota');

        $tables = config('permission.table_names');

        if (! Schema::hasTable($tables['permissions'])) {
            return;
        }

        $ids = DB::table($tables['permissions'])->where('name', 'manage sportsbook admin')->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table($tables['role_has_permissions'])->whereIn('permission_id', $ids)->delete();
        DB::table($tables['model_has_permissions'])->whereIn('permission_id', $ids)->delete();
        DB::table($tables['permissions'])->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Irreversible on purpose: the demo sportsbook and its admin page are gone.
     */
    public function down(): void
    {
        throw new RuntimeException('remove_demo_sportsbook_schema is irreversible. Restore from backup.');
    }
};
