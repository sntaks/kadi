<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The merged sportsbook (plan §5.2, Phase 7): one fresh set of sb_ tables, not the standalone app's history.
 * Every datetime is UTC (App\Sportsbook\Models\SportsbookModel). Staff references point to users (the old
 * admins table is folded into Kadi's roles). There is no local ledger: the standalone app's wallet transaction
 * columns are left out; bets are paid through the Kadi API from Phase 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sb_sports', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        Schema::create('sb_competitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained('sb_sports')->restrictOnDelete();
            $table->string('odds_api_key')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active_in_feed')->default(true);
            $table->boolean('is_synced')->default(false);
            $table->boolean('auto_settle')->default(true);
            $table->timestamp('scores_checked_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        Schema::create('sb_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('sb_competitions')->restrictOnDelete();
            $table->string('external_id')->unique();
            $table->string('home_team');
            $table->string('away_team');
            $table->timestamp('kicks_off_at')->index();
            $table->string('status')->default('scheduled');
            $table->timestamp('last_seen_in_feed_at')->nullable();
            $table->timestamps();
            $table->index(['competition_id', 'kicks_off_at']);
        });

        Schema::create('sb_markets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixture_id')->constrained('sb_fixtures')->cascadeOnDelete();
            $table->string('type');
            $table->decimal('line', 5, 2)->nullable();
            $table->string('key');
            $table->string('source')->default('feed');
            $table->string('status')->default('suspended');
            $table->string('suspension_reason')->nullable();
            $table->unsignedSmallInteger('bookmaker_count')->default(0);
            $table->timestamp('priced_at')->nullable();
            $table->timestamps();
            $table->unique(['fixture_id', 'key']);
            $table->index(['status', 'priced_at']);
        });

        Schema::create('sb_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_id')->constrained('sb_markets')->cascadeOnDelete();
            $table->string('outcome');
            $table->string('name');
            $table->decimal('fair_probability', 9, 6)->nullable();
            $table->decimal('feed_price', 8, 2)->nullable();
            $table->decimal('override_price', 8, 2)->nullable();
            $table->string('override_reason')->nullable();
            $table->foreignId('override_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('previous_price', 8, 2)->nullable();
            $table->timestamp('price_changed_at')->nullable();
            $table->timestamps();
            $table->unique(['market_id', 'outcome']);
        });

        Schema::create('sb_odds_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('selection_id')->constrained('sb_selections')->cascadeOnDelete();
            $table->decimal('price', 8, 2);
            $table->decimal('fair_probability', 9, 6)->nullable();
            $table->string('source');
            $table->timestamp('captured_at');
            $table->index(['selection_id', 'captured_at']);
        });

        Schema::create('sb_odds_api_calls', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint');
            $table->string('sport_key')->nullable();
            $table->unsignedInteger('credits_spent')->default(0);
            $table->unsignedInteger('credits_remaining')->nullable();
            $table->unsignedInteger('credits_used')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('succeeded');
            $table->string('error')->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::create('sb_booking_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->json('selection_ids');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('sb_fixture_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixture_id')->unique()->constrained('sb_fixtures')->restrictOnDelete();
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();
            $table->unsignedSmallInteger('half_time_home_score')->nullable();
            $table->unsignedSmallInteger('half_time_away_score')->nullable();
            $table->boolean('is_void')->default(false);
            $table->string('source', 10);
            $table->foreignId('recorded_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('sb_free_bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->decimal('min_odds', 8, 2);
            $table->timestamp('expires_at');
            $table->string('status', 12);
            $table->string('source', 12);
            $table->string('note')->nullable();
            // The staff member who gave it; who voided it is in the activity log.
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            // A replacement for a free bet whose bet ended void or was cancelled (constraint added below).
            $table->unsignedBigInteger('replaces_bet_id')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'expires_at']);
        });

        Schema::create('sb_bets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('free_bet_id')->nullable()->unique()->constrained('sb_free_bets')->restrictOnDelete();
            $table->string('reference', 12)->unique();
            $table->string('type', 10);
            $table->string('status', 12);
            $table->bigInteger('stake');
            $table->decimal('total_odds', 14, 2);
            $table->bigInteger('potential_payout');
            $table->unsignedTinyInteger('bonus_percent')->default(0);
            $table->bigInteger('payout')->nullable();
            $table->bigInteger('bonus')->nullable();
            $table->string('odds_change_policy', 10);
            // The slip's one-time key: a double tap on "Place bet" finds the bet already placed.
            $table->string('client_key', 64)->nullable();
            $table->unsignedSmallInteger('settlement_version')->default(0);
            $table->timestamp('placed_at')->index();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_key']);
            $table->index(['user_id', 'placed_at']);
            $table->index(['status', 'placed_at']);
        });

        Schema::table('sb_free_bets', function (Blueprint $table) {
            $table->foreign('replaces_bet_id')->references('id')->on('sb_bets')->restrictOnDelete();
        });

        Schema::create('sb_bet_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bet_id')->constrained('sb_bets')->cascadeOnDelete();
            $table->foreignId('selection_id')->nullable()->constrained('sb_selections')->nullOnDelete();
            $table->foreignId('market_id')->nullable()->constrained('sb_markets')->nullOnDelete();
            $table->foreignId('fixture_id')->constrained('sb_fixtures')->restrictOnDelete();
            $table->string('fixture_name');
            $table->timestamp('kicks_off_at');
            $table->string('market_type', 20);
            $table->decimal('market_line', 5, 2)->nullable();
            $table->string('market_name');
            $table->string('outcome', 20);
            $table->string('selection_name');
            $table->decimal('price', 8, 2);
            $table->string('status', 10);
            $table->string('void_reason')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['fixture_id', 'status']);
        });

        Schema::create('sb_jackpots', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('status', 12);
            $table->bigInteger('entry_price');
            // [{correct, pool}]: each tier's pool (cents) is split equally between the entries with exactly that many right.
            $table->json('prize_tiers');
            // More void matches than this cancels the jackpot and refunds every entry.
            $table->unsignedTinyInteger('void_limit');
            $table->unsignedInteger('settlement_version')->default(1);
            $table->string('cancel_reason')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('sb_jackpot_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jackpot_id')->constrained('sb_jackpots')->cascadeOnDelete();
            $table->foreignId('fixture_id')->constrained('sb_fixtures')->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('fixture_name');
            // The kick-off when the jackpot was published: a match moved more than postponement_void_hours from it is void.
            $table->timestamp('kicks_off_at');
            // "1", "X" or "2" once the full-time result is in.
            $table->string('outcome', 1)->nullable();
            $table->boolean('is_void')->default(false);
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->unique(['jackpot_id', 'fixture_id']);
            $table->unique(['jackpot_id', 'position']);
        });

        Schema::create('sb_jackpot_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jackpot_id')->constrained('sb_jackpots')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reference', 12)->unique();
            $table->string('status', 12);
            $table->bigInteger('stake');
            $table->unsignedTinyInteger('correct_count')->nullable();
            $table->bigInteger('prize')->nullable();
            // The entry form's one-time key: a double tap on "Enter" finds the entry already made.
            $table->string('client_key', 64)->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_key']);
            $table->index(['user_id', 'placed_at']);
            $table->index(['jackpot_id', 'status']);
        });

        Schema::create('sb_jackpot_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jackpot_entry_id')->constrained('sb_jackpot_entries')->cascadeOnDelete();
            $table->foreignId('jackpot_fixture_id')->constrained('sb_jackpot_fixtures')->restrictOnDelete();
            $table->string('pick', 1);
            $table->unique(['jackpot_entry_id', 'jackpot_fixture_id']);
        });

        // A player's choice when a price moves before their bet is placed (plan §5.2).
        Schema::table('users', function (Blueprint $table) {
            $table->string('odds_change_policy', 10)->default('higher');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('odds_change_policy');
        });

        Schema::table('sb_free_bets', function (Blueprint $table) {
            $table->dropForeign(['replaces_bet_id']);
        });

        foreach (['sb_jackpot_picks', 'sb_jackpot_entries', 'sb_jackpot_fixtures', 'sb_jackpots', 'sb_bet_legs', 'sb_bets', 'sb_free_bets',
            'sb_fixture_results', 'sb_booking_codes', 'sb_odds_api_calls', 'sb_odds_snapshots', 'sb_selections', 'sb_markets',
            'sb_fixtures', 'sb_competitions', 'sb_sports'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
