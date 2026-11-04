<?php

namespace App\Sportsbook\Betting\Jackpot;

use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Models\JackpotPick;
use App\Sportsbook\Wagers\WagerOps;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Settles an open jackpot once every match has a result or is void:
 * - a void match counts as right for every entry;
 * - more void matches than the jackpot's void limit cancels it instead, refunding every entry;
 * - each prize tier's pool is split equally (to the cent, rounded down) between the entries with exactly that many
 *   right. A pool nobody wins is not paid out.
 *
 * Each entry's prize (or loss) is a settle op of the jackpot's settlement version, written with the decision; the API
 * withholds winnings tax. While any entry is still being checked with the wallet, the jackpot waits, so the prize
 * split is never made without it.
 */
class SettleJackpot
{
    public function __construct(private WagerOps $ops, private CancelJackpot $cancelJackpot) {}

    public function handle(Jackpot $jackpot): Jackpot
    {
        return DB::transaction(function () use ($jackpot): Jackpot {
            $jackpot = Jackpot::query()->whereKey($jackpot->id)->lockForUpdate()->firstOrFail();

            if ($jackpot->status !== JackpotStatus::Open) {
                return $jackpot;
            }

            $matches = $jackpot->jackpotFixtures()->get();

            if ($matches->isEmpty() || $matches->contains(fn ($match): bool => ! $match->isDecided())) {
                return $jackpot;
            }

            if ($jackpot->entries()->where('status', JackpotEntryStatus::Pending)->exists()) {
                return $jackpot;
            }

            $voids = $matches->where('is_void', true)->count();

            if ($voids > $jackpot->void_limit) {
                return $this->cancelJackpot->handle($jackpot, __(':count of :matches matches were void, more than the limit of :limit.', [
                    'count' => $voids,
                    'matches' => $matches->count(),
                    'limit' => $jackpot->void_limit,
                ]));
            }

            $correct = self::correctCounts($jackpot);
            $prizes = collect(self::tiers($jackpot, $correct))->pluck('each', 'correct');
            $winners = 0;

            foreach ($jackpot->entries()->where('status', JackpotEntryStatus::Open)->lockForUpdate()->get() as $entry) {
                $count = (int) $correct->get($entry->id, 0);
                $winners += $this->settleEntry($jackpot, $entry, $count, (int) $prizes->get($count, 0)) ? 1 : 0;
            }

            $this->ops->sendAfterCommit();
            $jackpot->update(['status' => JackpotStatus::Settled, 'settled_at' => now('UTC')]);

            activity('settlement')
                ->performedOn($jackpot)
                ->event('jackpot-settled')
                ->withProperties(['jackpot' => $jackpot->name, 'winners' => $winners, 'void_matches' => $voids, 'version' => $jackpot->settlement_version])
                ->log('Jackpot settled');

            return $jackpot;
        });
    }

    /**
     * Each tier with how many entries have exactly that many right so far and what each would get, counting matches
     * still to be decided as wrong. Settlement uses this once every match is decided; before that it is a preview.
     *
     * @param  Collection<int, int>|null  $correct  Entry id => matches right, when already counted.
     * @return list<array{correct: int, pool: int, winners: int, each: int}>
     */
    public static function tiers(Jackpot $jackpot, ?Collection $correct = null): array
    {
        $correct ??= self::correctCounts($jackpot);

        return collect($jackpot->prize_tiers)
            ->map(function (array $tier) use ($correct): array {
                $winners = $correct->filter(fn (int $count): bool => $count === (int) $tier['correct'])->count();

                return [
                    'correct' => (int) $tier['correct'],
                    'pool' => (int) $tier['pool'],
                    'winners' => $winners,
                    'each' => $winners === 0 ? 0 : intdiv((int) $tier['pool'], $winners),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * How many matches each placed entry (open or settled; never pending, rejected or refunded) has right, counted
     * in the database.
     *
     * @return Collection<int, int> Entry id => matches right.
     */
    public static function correctCounts(Jackpot $jackpot): Collection
    {
        return JackpotPick::query()
            ->join('sb_jackpot_fixtures', 'sb_jackpot_fixtures.id', '=', 'sb_jackpot_picks.jackpot_fixture_id')
            ->join('sb_jackpot_entries', 'sb_jackpot_entries.id', '=', 'sb_jackpot_picks.jackpot_entry_id')
            ->where('sb_jackpot_entries.jackpot_id', $jackpot->id)
            ->whereIn('sb_jackpot_entries.status', [JackpotEntryStatus::Open, JackpotEntryStatus::Won, JackpotEntryStatus::Lost])
            ->groupBy('sb_jackpot_picks.jackpot_entry_id')
            ->selectRaw('sb_jackpot_picks.jackpot_entry_id, sum(case when sb_jackpot_fixtures.is_void = ? or sb_jackpot_fixtures.outcome = sb_jackpot_picks.pick then 1 else 0 end) as correct', [true])
            ->pluck('correct', 'jackpot_entry_id')
            ->map(fn (mixed $count): int => (int) $count);
    }

    /**
     * Record the entry's prize, or close it as lost, with its settle op. True when it won something.
     */
    private function settleEntry(Jackpot $jackpot, JackpotEntry $entry, int $correct, int $prize): bool
    {
        if ($prize === 0) {
            $entry->update(['status' => JackpotEntryStatus::Lost, 'correct_count' => $correct, 'prize' => 0, 'settled_at' => now('UTC')]);
            $this->ops->settle($entry, $jackpot->settlement_version, 'lost', 0);

            return false;
        }

        $entry->update(['status' => JackpotEntryStatus::Won, 'correct_count' => $correct, 'prize' => $prize, 'tax' => null, 'settled_at' => now('UTC')]);
        $this->ops->settle($entry, $jackpot->settlement_version, 'won', $prize, reason: "{$correct} right");

        return true;
    }
}
