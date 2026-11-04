<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 (plan §5.3): sports bets and jackpot entries are wagers at the Kadi API.
 *
 * - `wager_reference` is the UUID the API knows the wager by (the short `reference` stays for support).
 * - `reject_reason` is the API's refusal code for a bet that was never placed.
 * - `tax` is the winnings tax the API withheld, read from its settle response.
 * - `sb_wager_ops` is the outbox of settlements and reversals, sent to the API in order per wager.
 * - A closed account keeps its bets and entries for the books, without the player (`user_id` set to null).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['sb_bets', 'sb_jackpot_entries'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->uuid('wager_reference')->nullable()->unique()->after('reference');
                $table->string('reject_reason', 40)->nullable()->after('status');
                $table->bigInteger('tax')->nullable();
            });
        }

        foreach (['sb_bets', 'sb_jackpot_entries', 'sb_free_bets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        Schema::create('sb_wager_ops', function (Blueprint $table) {
            $table->id();
            $table->string('product', 50);
            $table->uuid('wager_reference');
            // The bet or jackpot entry it settles: "bet" or "jackpot_entry", and its id.
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->string('operation', 10);
            $table->unsignedInteger('version');
            $table->json('payload');
            $table->char('fingerprint', 64);
            $table->string('status', 10)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->string('last_error', 60)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['product', 'wager_reference', 'operation', 'version'], 'sb_wager_ops_natural_key');
            $table->index(['status', 'next_attempt_at']);
            $table->index(['wager_reference', 'id']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sb_wager_ops');

        foreach (['sb_bets', 'sb_jackpot_entries', 'sb_free_bets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
                $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            });
        }

        foreach (['sb_bets', 'sb_jackpot_entries'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['wager_reference']);
                $table->dropColumn(['wager_reference', 'reject_reason', 'tax']);
            });
        }
    }
};
