<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live bets (plan §4.1, §4.4, §11.1). A bet with any in-play leg is held until `accept_after`, checked again, then
 * placed on its own Kadi API product (`sportsbook_live`), which every later op uses too. Each leg records whether it
 * was in play and the score when it was placed, for support, disputes and GMS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sb_bets', function (Blueprint $table) {
            $table->string('product', 40)->nullable()->after('wager_reference');
            $table->timestamp('accept_after')->nullable()->after('placed_at');
        });

        Schema::table('sb_bet_legs', function (Blueprint $table) {
            $table->boolean('in_play')->default(false)->after('price');
            $table->string('score_at_placement', 9)->nullable()->after('in_play');
        });
    }

    public function down(): void
    {
        Schema::table('sb_bet_legs', function (Blueprint $table) {
            $table->dropColumn(['in_play', 'score_at_placement']);
        });

        Schema::table('sb_bets', function (Blueprint $table) {
            $table->dropColumn(['product', 'accept_after']);
        });
    }
};
