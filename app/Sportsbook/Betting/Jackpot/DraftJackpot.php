<?php

namespace App\Sportsbook\Betting\Jackpot;

use App\Models\User;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Jackpot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a draft jackpot or changes one. Players never see a draft; PublishJackpot checks it is complete. The
 * matches are numbered in kick-off order and their names and kick-offs are copied at the same time.
 */
class DraftJackpot
{
    /**
     * @param  array{name: string, entry_price: int, void_limit: int, prize_tiers: list<array{correct: int|string, pool: int}>, fixture_ids: list<int|string>}  $data  Amounts in cents.
     *
     * @throws ValidationException
     */
    public function handle(array $data, ?Jackpot $jackpot = null, ?User $admin = null): Jackpot
    {
        return DB::transaction(function () use ($data, $jackpot, $admin): Jackpot {
            if ($jackpot !== null) {
                $jackpot = Jackpot::query()->whereKey($jackpot->id)->lockForUpdate()->firstOrFail();

                if ($jackpot->status !== JackpotStatus::Draft) {
                    throw ValidationException::withMessages(['jackpot' => __('Only a draft jackpot can be changed.')]);
                }
            }

            $fixtureIds = array_values(array_unique(array_map('intval', $data['fixture_ids'])));
            $fixtures = Fixture::query()->upcoming()->whereKey($fixtureIds)->orderBy('kicks_off_at')->orderBy('id')->get();

            if ($fixtures->count() !== count($fixtureIds)) {
                throw ValidationException::withMessages(['fixture_ids' => __('Pick only matches that have not kicked off.')]);
            }

            $attributes = [
                'name' => $data['name'],
                'entry_price' => $data['entry_price'],
                'void_limit' => $data['void_limit'],
                'prize_tiers' => collect($data['prize_tiers'])
                    ->map(fn (array $tier): array => ['correct' => (int) $tier['correct'], 'pool' => (int) $tier['pool']])
                    ->sortByDesc('correct')
                    ->values()
                    ->all(),
            ];

            if ($jackpot === null) {
                $jackpot = Jackpot::create([...$attributes, 'status' => JackpotStatus::Draft]);
            } else {
                $jackpot->update($attributes);
                $jackpot->jackpotFixtures()->delete();
            }

            $jackpot->jackpotFixtures()->createMany($fixtures->values()->map(fn (Fixture $fixture, int $index): array => [
                'fixture_id' => $fixture->id,
                'position' => $index + 1,
                'fixture_name' => $fixture->name(),
                'kicks_off_at' => $fixture->kicks_off_at,
            ])->all());

            activity('settlement')
                ->causedBy($admin)
                ->performedOn($jackpot)
                ->event($jackpot->wasRecentlyCreated ? 'jackpot-drafted' : 'jackpot-changed')
                ->withProperties(['jackpot' => $jackpot->name, 'matches' => $fixtures->count()])
                ->log($jackpot->wasRecentlyCreated ? 'Jackpot drafted' : 'Jackpot changed');

            return $jackpot;
        });
    }
}
