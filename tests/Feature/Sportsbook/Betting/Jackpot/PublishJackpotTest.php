<?php

use App\Sportsbook\Betting\Jackpot\DraftJackpot;
use App\Sportsbook\Betting\Jackpot\PublishJackpot;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Jackpot;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    config(['sportsbook.jackpot.min_matches' => 3, 'sportsbook.jackpot.max_matches' => 5]);
});

test('publishing opens the draft and copies each match\'s current kick-off', function () {
    $draft = Jackpot::factory()->withMatches(3)->create();
    $match = $draft->jackpotFixtures()->first();
    $match->fixture->update(['kicks_off_at' => $match->kicks_off_at->addMinutes(30)]);

    app(PublishJackpot::class)->handle($draft);

    expect($draft->fresh())->status->toBe(JackpotStatus::Open)->published_at->not->toBeNull()
        ->and($match->fresh()->kicks_off_at->equalTo($match->fixture->fresh()->kicks_off_at))->toBeTrue()
        ->and(Activity::query()->where('event', 'jackpot-published')->exists())->toBeTrue();
});

test('an incomplete draft is not published', function (Closure $incomplete, string $message) {
    $draft = Jackpot::factory()->withMatches(3)->create();
    $incomplete($draft);

    expect(fn () => app(PublishJackpot::class)->handle($draft))->toThrow(ValidationException::class, $message)
        ->and($draft->fresh()->status)->toBe(JackpotStatus::Draft);
})->with([
    'too few matches' => [fn (Jackpot $draft) => $draft->jackpotFixtures()->first()->delete(), 'A jackpot needs between 3 and 5 matches; this one has 2.'],
    'a match kicked off' => [fn (Jackpot $draft) => $draft->jackpotFixtures()->first()->fixture->update(['kicks_off_at' => now()->subMinute()]), 'has kicked off or been cancelled'],
    'no entry price' => [fn (Jackpot $draft) => $draft->update(['entry_price' => 0]), 'Set an entry price.'],
    'no tiers' => [fn (Jackpot $draft) => $draft->update(['prize_tiers' => []]), 'Add at least one prize tier.'],
    'two tiers for the same number right' => [fn (Jackpot $draft) => $draft->update(['prize_tiers' => [['correct' => 3, 'pool' => 100], ['correct' => 3, 'pool' => 50]]]), 'Each prize tier needs a different number of matches right.'],
    'a tier for more matches than there are' => [fn (Jackpot $draft) => $draft->update(['prize_tiers' => [['correct' => 4, 'pool' => 100], ['correct' => 3, 'pool' => 50]]]), 'Prize tiers must be for 1 to 3 matches right.'],
    'a tier with no prize' => [fn (Jackpot $draft) => $draft->update(['prize_tiers' => [['correct' => 3, 'pool' => 0]]]), 'Every prize tier needs a prize.'],
    'no tier for every match right' => [fn (Jackpot $draft) => $draft->update(['prize_tiers' => [['correct' => 2, 'pool' => 100]]]), 'Add a prize tier for all 3 matches right.'],
]);

test('only a draft is published', function () {
    expect(fn () => app(PublishJackpot::class)->handle(openJackpot()))->toThrow(ValidationException::class, 'Only a draft jackpot can be published.');
});

test('a draft can only have matches that have not kicked off', function () {
    $started = Fixture::factory()->create(['kicks_off_at' => now()->subHour()]);

    expect(fn () => app(DraftJackpot::class)->handle([
        'name' => 'Weekend Jackpot',
        'entry_price' => 9_900,
        'void_limit' => 1,
        'prize_tiers' => [['correct' => 1, 'pool' => 100]],
        'fixture_ids' => [$started->id],
    ]))->toThrow(ValidationException::class, 'Pick only matches that have not kicked off.');

    expect(Jackpot::count())->toBe(0);
});

test('a published jackpot cannot be changed', function () {
    $jackpot = openJackpot();

    expect(fn () => app(DraftJackpot::class)->handle([
        'name' => 'Renamed',
        'entry_price' => 100,
        'void_limit' => 1,
        'prize_tiers' => [['correct' => 3, 'pool' => 100]],
        'fixture_ids' => [],
    ], $jackpot))->toThrow(ValidationException::class, 'Only a draft jackpot can be changed.');
});
