<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * platform:health counts the last few minutes of launches across every player: an index on the time alone keeps that
 * a short range scan as the table grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_launches', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_launches', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
