<?php

use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;

test('lists 1X2 selections as home, draw, away whatever order they were stored in', function () {
    $market = Market::factory()->create();
    Selection::factory()->for($market)->create(['outcome' => 'away']);
    Selection::factory()->for($market)->create(['outcome' => 'home']);
    Selection::factory()->for($market)->create(['outcome' => 'draw']);

    expect($market->selections->pluck('outcome')->all())->toBe(['home', 'draw', 'away'])
        ->and(Market::with('selections')->find($market->id)->selections->pluck('outcome')->all())->toBe(['home', 'draw', 'away']);
});

test('lists over before under on a goal line', function () {
    $market = Market::factory()->create(['type' => MarketType::Totals, 'line' => 2.5, 'key' => 'totals:2.5']);
    Selection::factory()->for($market)->create(['outcome' => 'under']);
    Selection::factory()->for($market)->create(['outcome' => 'over']);

    expect($market->selections->pluck('outcome')->all())->toBe(['over', 'under']);
});
