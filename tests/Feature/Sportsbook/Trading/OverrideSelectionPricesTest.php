<?php

use App\Models\User;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\OddsSnapshot;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Trading\OverrideSelectionPrices;
use Spatie\Activitylog\Models\Activity;

test('overrides a price, records who did it and why, and logs the change', function () {
    $admin = User::factory()->create();
    $market = Market::factory()->create();
    $home = Selection::factory()->for($market)->create(['outcome' => 'home', 'name' => 'Arsenal', 'feed_price' => '1.88']);

    app(OverrideSelectionPrices::class)->handle($market, $admin, [$home->id => '2.10'], 'Key striker ruled out');

    $home->refresh();
    $activity = Activity::query()->where('event', 'prices-overridden')->sole();
    expect($home->price())->toBe('2.10')
        ->and($home->override_reason)->toBe('Key striker ruled out')
        ->and($home->overrideBy->is($admin))->toBeTrue()
        ->and(OddsSnapshot::where('selection_id', $home->id)->sole()->only(['price', 'source']))->toBe(['price' => '2.10', 'source' => 'override'])
        ->and($activity->causer->is($admin))->toBeTrue()
        ->and($activity->properties->get('prices'))->toBe(['Arsenal' => ['from' => '1.88', 'to' => '2.10']]);
});

test('clearing an override returns the selection to the feed price', function () {
    $admin = User::factory()->create();
    $market = Market::factory()->create();
    $home = Selection::factory()->for($market)->overridden('2.10')->create(['feed_price' => '1.88']);

    app(OverrideSelectionPrices::class)->handle($market, $admin, [$home->id => null], 'Team news confirmed');

    $home->refresh();
    expect($home->price())->toBe('1.88')
        ->and($home->override_reason)->toBeNull()
        ->and($home->override_by_admin_id)->toBeNull()
        ->and(OddsSnapshot::where('selection_id', $home->id)->sole()->only(['price', 'source']))->toBe(['price' => '1.88', 'source' => 'feed']);
});

test('rejects prices outside 1.01 to 1000 and changes nothing', function (string $price) {
    $market = Market::factory()->create();
    $home = Selection::factory()->for($market)->create(['feed_price' => '1.88']);

    expect(fn () => app(OverrideSelectionPrices::class)->handle($market, User::factory()->create(), [$home->id => $price], 'Typo'))
        ->toThrow(InvalidArgumentException::class);

    expect($home->fresh()->override_price)->toBeNull()
        ->and(Activity::query()->where('event', 'prices-overridden')->exists())->toBeFalse();
})->with(['evens or shorter' => '1.00', 'absurdly long' => '1000.01', 'not a number' => 'abc']);

test('ignores selection ids from another market', function () {
    $market = Market::factory()->create();
    $otherMarketSelection = Selection::factory()->create(['feed_price' => '3.00']);

    app(OverrideSelectionPrices::class)->handle($market, User::factory()->create(), [$otherMarketSelection->id => '9.99'], 'Wrong market');

    expect($otherMarketSelection->fresh()->override_price)->toBeNull();
});
