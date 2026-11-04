<?php

namespace App\Sportsbook\Betting\Jackpot;

use App\Models\User;
use App\Sportsbook\Enums\FixtureStatus;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opens a draft jackpot to entries once it is complete: between `min_matches` and `max_matches` matches, none
 * kicked off, an entry price, and prize tiers for distinct numbers of matches right. Kick-offs are copied again at
 * publishing, since postponement is measured from them.
 */
class PublishJackpot
{
    /**
     * @throws ValidationException
     */
    public function handle(Jackpot $jackpot, ?User $admin = null): Jackpot
    {
        return DB::transaction(function () use ($jackpot, $admin): Jackpot {
            $jackpot = Jackpot::query()->whereKey($jackpot->id)->lockForUpdate()->firstOrFail();

            if ($jackpot->status !== JackpotStatus::Draft) {
                throw ValidationException::withMessages(['jackpot' => __('Only a draft jackpot can be published.')]);
            }

            $matches = $jackpot->jackpotFixtures()->with('fixture')->get();
            $min = (int) config('sportsbook.jackpot.min_matches');
            $max = (int) config('sportsbook.jackpot.max_matches');

            if ($matches->count() < $min || $matches->count() > $max) {
                throw ValidationException::withMessages(['jackpot' => __('A jackpot needs between :min and :max matches; this one has :count.', ['min' => $min, 'max' => $max, 'count' => $matches->count()])]);
            }

            $started = $matches->first(fn ($match): bool => $match->fixture->status !== FixtureStatus::Scheduled || $match->fixture->hasKickedOff());

            if ($started !== null) {
                throw ValidationException::withMessages(['jackpot' => __(':match has kicked off or been cancelled. Take it out first.', ['match' => $started->fixture_name])]);
            }

            $this->ensureTiersAreValid($jackpot, $matches->count());

            foreach ($matches as $match) {
                $match->update(['fixture_name' => $match->fixture->name(), 'kicks_off_at' => $match->fixture->kicks_off_at]);
            }

            $jackpot->update(['status' => JackpotStatus::Open, 'published_at' => now('UTC')]);

            activity('settlement')
                ->causedBy($admin)
                ->performedOn($jackpot)
                ->event('jackpot-published')
                ->withProperties(['jackpot' => $jackpot->name, 'matches' => $matches->count(), 'entry_price' => Money::format($jackpot->entry_price), 'prizes' => Money::format($jackpot->totalPrizes())])
                ->log('Jackpot published');

            return $jackpot;
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureTiersAreValid(Jackpot $jackpot, int $matchCount): void
    {
        $tiers = collect($jackpot->prize_tiers);
        $correct = $tiers->pluck('correct')->map(fn (mixed $count): int => (int) $count);

        $problem = match (true) {
            $jackpot->entry_price <= 0 => __('Set an entry price.'),
            $tiers->isEmpty() => __('Add at least one prize tier.'),
            $correct->unique()->count() !== $correct->count() => __('Each prize tier needs a different number of matches right.'),
            $correct->contains(fn (int $count): bool => $count < 1 || $count > $matchCount) => __('Prize tiers must be for 1 to :count matches right.', ['count' => $matchCount]),
            $tiers->contains(fn (array $tier): bool => (int) $tier['pool'] <= 0) => __('Every prize tier needs a prize.'),
            ! $correct->contains($matchCount) => __('Add a prize tier for all :count matches right.', ['count' => $matchCount]),
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages(['prize_tiers' => $problem]);
        }
    }
}
