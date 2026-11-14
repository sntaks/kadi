<?php

use App\Sportsbook\Betting\Settlement\MarketRules;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\BetLeg;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\FixtureResult;
use App\Sportsbook\Models\Sport;

function legOn(MarketType $type, string $outcome, ?string $line = null): BetLeg
{
    return new BetLeg(['market_type' => $type, 'outcome' => $outcome, 'market_line' => $line]);
}

function fullTime(int $home, int $away, ?int $halfTimeHome = null, ?int $halfTimeAway = null): FixtureResult
{
    return new FixtureResult(['home_score' => $home, 'away_score' => $away, 'half_time_home_score' => $halfTimeHome, 'half_time_away_score' => $halfTimeAway, 'is_void' => false]);
}

test('settles each market from the full-time score', function (BetLeg $leg, FixtureResult $result, ?BetLegStatus $expected) {
    expect(MarketRules::settle($leg, $result))->toBe($expected);
})->with([
    '1X2 home wins' => [fn () => legOn(MarketType::MatchResult, 'home'), fn () => fullTime(2, 1), BetLegStatus::Won],
    '1X2 home loses to a draw' => [fn () => legOn(MarketType::MatchResult, 'home'), fn () => fullTime(1, 1), BetLegStatus::Lost],
    '1X2 draw' => [fn () => legOn(MarketType::MatchResult, 'draw'), fn () => fullTime(0, 0), BetLegStatus::Won],
    '1X2 away' => [fn () => legOn(MarketType::MatchResult, 'away'), fn () => fullTime(0, 3), BetLegStatus::Won],
    'double chance 1X on a draw' => [fn () => legOn(MarketType::DoubleChance, 'home_draw'), fn () => fullTime(2, 2), BetLegStatus::Won],
    'double chance 1X on an away win' => [fn () => legOn(MarketType::DoubleChance, 'home_draw'), fn () => fullTime(0, 1), BetLegStatus::Lost],
    'double chance 12 on a draw' => [fn () => legOn(MarketType::DoubleChance, 'home_away'), fn () => fullTime(1, 1), BetLegStatus::Lost],
    'double chance X2 on an away win' => [fn () => legOn(MarketType::DoubleChance, 'draw_away'), fn () => fullTime(1, 2), BetLegStatus::Won],
    'draw no bet home wins' => [fn () => legOn(MarketType::DrawNoBet, 'home'), fn () => fullTime(3, 0), BetLegStatus::Won],
    'draw no bet away loses' => [fn () => legOn(MarketType::DrawNoBet, 'away'), fn () => fullTime(3, 0), BetLegStatus::Lost],
    'draw no bet on a draw is void' => [fn () => legOn(MarketType::DrawNoBet, 'home'), fn () => fullTime(1, 1), BetLegStatus::Void],
    'both teams score yes' => [fn () => legOn(MarketType::BothTeamsToScore, 'yes'), fn () => fullTime(1, 1), BetLegStatus::Won],
    'both teams score yes, one blank' => [fn () => legOn(MarketType::BothTeamsToScore, 'yes'), fn () => fullTime(2, 0), BetLegStatus::Lost],
    'both teams score no on 0-0' => [fn () => legOn(MarketType::BothTeamsToScore, 'no'), fn () => fullTime(0, 0), BetLegStatus::Won],
    'over 2.5 with three goals' => [fn () => legOn(MarketType::Totals, 'over', '2.50'), fn () => fullTime(2, 1), BetLegStatus::Won],
    'under 2.5 with three goals' => [fn () => legOn(MarketType::Totals, 'under', '2.50'), fn () => fullTime(2, 1), BetLegStatus::Lost],
    'under 1.5 with one goal' => [fn () => legOn(MarketType::Totals, 'under', '1.50'), fn () => fullTime(0, 1), BetLegStatus::Won],
    'over 2 landing on two goals is a push' => [fn () => legOn(MarketType::Totals, 'over', '2.00'), fn () => fullTime(1, 1), BetLegStatus::Void],
    'a quarter goal line is left for an admin' => [fn () => legOn(MarketType::Totals, 'over', '2.25'), fn () => fullTime(1, 1), null],
    'handicap home -1.5 winning by two' => [fn () => legOn(MarketType::Spreads, 'home', '-1.50'), fn () => fullTime(2, 0), BetLegStatus::Won],
    'handicap home -1.5 winning by one' => [fn () => legOn(MarketType::Spreads, 'home', '-1.50'), fn () => fullTime(1, 0), BetLegStatus::Lost],
    'handicap away +1.5 losing by one' => [fn () => legOn(MarketType::Spreads, 'away', '-1.50'), fn () => fullTime(1, 0), BetLegStatus::Won],
    'handicap home -1 winning by one is a push' => [fn () => legOn(MarketType::Spreads, 'home', '-1.00'), fn () => fullTime(2, 1), BetLegStatus::Void],
    'handicap away +1 losing by one is a push' => [fn () => legOn(MarketType::Spreads, 'away', '-1.00'), fn () => fullTime(2, 1), BetLegStatus::Void],
    'handicap home -1 winning by two' => [fn () => legOn(MarketType::Spreads, 'home', '-1.00'), fn () => fullTime(3, 1), BetLegStatus::Won],
    'handicap 0 on a draw is a push' => [fn () => legOn(MarketType::Spreads, 'home', '0.00'), fn () => fullTime(1, 1), BetLegStatus::Void],
    'handicap away 0 winning' => [fn () => legOn(MarketType::Spreads, 'away', '0.00'), fn () => fullTime(0, 2), BetLegStatus::Won],
    'handicap home +0.5 on a draw' => [fn () => legOn(MarketType::Spreads, 'home', '0.50'), fn () => fullTime(2, 2), BetLegStatus::Won],
    'handicap away -0.5 on a draw' => [fn () => legOn(MarketType::Spreads, 'away', '0.50'), fn () => fullTime(2, 2), BetLegStatus::Lost],
    'handicap home +2 losing by three' => [fn () => legOn(MarketType::Spreads, 'home', '2.00'), fn () => fullTime(0, 3), BetLegStatus::Lost],
    'home team over 1.5 with two home goals' => [fn () => legOn(MarketType::HomeTeamTotals, 'over', '1.50'), fn () => fullTime(2, 3), BetLegStatus::Won],
    'home team under 1.5 counts only home goals' => [fn () => legOn(MarketType::HomeTeamTotals, 'under', '1.50'), fn () => fullTime(1, 4), BetLegStatus::Won],
    'away team over 0.5 kept out' => [fn () => legOn(MarketType::AwayTeamTotals, 'over', '0.50'), fn () => fullTime(3, 0), BetLegStatus::Lost],
    'away team over 2 landing on two is a push' => [fn () => legOn(MarketType::AwayTeamTotals, 'over', '2.00'), fn () => fullTime(0, 2), BetLegStatus::Void],
    'a quarter handicap is left for an admin' => [fn () => legOn(MarketType::Spreads, 'home', '-0.75'), fn () => fullTime(1, 0), null],
    'correct score exact' => [fn () => legOn(MarketType::CorrectScore, '2-1'), fn () => fullTime(2, 1), BetLegStatus::Won],
    'correct score wrong' => [fn () => legOn(MarketType::CorrectScore, '2-1'), fn () => fullTime(1, 2), BetLegStatus::Lost],
    'any other score: 5-0' => [fn () => legOn(MarketType::CorrectScore, 'other'), fn () => fullTime(5, 0), BetLegStatus::Won],
    'any other score: 4-4 is listed' => [fn () => legOn(MarketType::CorrectScore, 'other'), fn () => fullTime(4, 4), BetLegStatus::Lost],
    'HT/FT draw then home' => [fn () => legOn(MarketType::HalfTimeFullTime, 'X/1'), fn () => fullTime(2, 1, 0, 0), BetLegStatus::Won],
    'HT/FT home/home when it was X at half-time' => [fn () => legOn(MarketType::HalfTimeFullTime, '1/1'), fn () => fullTime(2, 1, 0, 0), BetLegStatus::Lost],
    'HT/FT waits without a half-time score' => [fn () => legOn(MarketType::HalfTimeFullTime, 'X/1'), fn () => fullTime(2, 1), null],
]);

test('every leg on a void match is void', function (MarketType $type, string $outcome) {
    expect(MarketRules::settle(legOn($type, $outcome, '2.50'), new FixtureResult(['is_void' => true])))->toBe(BetLegStatus::Void);
})->with([
    [MarketType::MatchResult, 'home'],
    [MarketType::CorrectScore, '1-0'],
    [MarketType::HalfTimeFullTime, '1/1'],
]);

test('a basketball Winner settles on the final score incl. overtime, and a level score voids it', function (int $home, int $away, string $outcome, BetLegStatus $expected) {
    $fixture = Fixture::factory()->create([
        'competition_id' => Competition::factory()->create(['sport_id' => Sport::factory()->configured('basketball')]),
    ]);
    $leg = legOn(MarketType::MatchResult, $outcome);
    $leg->fixture_id = $fixture->id;

    expect(MarketRules::settle($leg, fullTime($home, $away)))->toBe($expected);
})->with([
    'home wins after overtime' => [118, 112, 'home', BetLegStatus::Won],
    'away loses' => [118, 112, 'away', BetLegStatus::Lost],
    'recorded level: void, never lost' => [100, 100, 'home', BetLegStatus::Void],
]);
