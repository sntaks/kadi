<?php

namespace App\Sportsbook\Betting\Jackpot;

use App\Sportsbook\Betting\SportsWallet;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Models\JackpotFixture;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Voids jackpot matches that moved more than `postponement_void_hours` (48) from the kick-off the jackpot was
 * published with, then settles the jackpot if that was the last match it was waiting for. Like a postponed bet leg,
 * the match stays void even if a result comes in later.
 */
class VoidPostponedJackpotMatches
{
    public function __construct(private SportsWallet $wallet) {}

    /**
     * @return int How many jackpot matches were voided.
     */
    public function handle(): int
    {
        $hours = (int) config('sportsbook.settlement.postponement_void_hours');
        $voided = 0;

        $matches = JackpotFixture::query()
            ->whereNull('sb_jackpot_fixtures.void_reason')
            ->whereNull('sb_jackpot_fixtures.outcome')
            ->where('sb_jackpot_fixtures.is_void', false)
            ->whereHas('jackpot', fn ($query) => $query->where('status', JackpotStatus::Open))
            ->join('sb_fixtures', 'sb_fixtures.id', '=', 'sb_jackpot_fixtures.fixture_id')
            ->whereColumn('sb_fixtures.kicks_off_at', '!=', 'sb_jackpot_fixtures.kicks_off_at')
            ->select('sb_jackpot_fixtures.*', 'sb_fixtures.kicks_off_at as fixture_kicks_off_at')
            ->with('jackpot')
            ->get();

        foreach ($matches as $match) {
            $kickOff = $match->getAttribute('fixture_kicks_off_at');

            if (! is_string($kickOff) || Date::parse($kickOff, 'UTC')->diffInHours($match->kicks_off_at, true) <= $hours) {
                continue;
            }

            // Void and settle together: if settling fails, the match stays open and the next run tries again.
            // One jackpot that cannot settle never stops the others (or the result fetch after this).
            try {
                DB::transaction(function () use ($match, $hours): void {
                    $match->update([
                        'is_void' => true,
                        'void_reason' => __('Match moved more than :hours hours from its published kick-off.', ['hours' => $hours]),
                    ]);
                    $this->wallet->settleJackpot($match->jackpot);
                });
            } catch (Throwable $e) {
                report($e);
                Log::warning("Jackpot {$match->jackpot_id} match {$match->id} not voided: ".class_basename($e));

                continue;
            }

            $voided++;
        }

        return $voided;
    }
}
