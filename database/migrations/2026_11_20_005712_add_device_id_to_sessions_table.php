<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The browser's device id (App\Auth\DeviceId) on each session, so the one-device rule can tell the same device signing
 * in again from a different one. Written by App\Session\DeviceSessionHandler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('session.table', 'sessions'), function (Blueprint $table) {
            $table->string('device_id', 40)->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table(config('session.table', 'sessions'), function (Blueprint $table) {
            $table->dropIndex(['device_id']);
            $table->dropColumn('device_id');
        });
    }
};
