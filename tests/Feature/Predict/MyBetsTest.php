<?php

use App\Livewire\Predict\MyBets;
use App\Models\User;
use App\Predict\MarketFeed;
use App\Predict\MyBetsCache;
use App\Predict\PendingAttempt;
use App\Support\SiteNavigation;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * My bets (/predictions/bets, App\Livewire\Predict\MyBets and App\Predict\MyBets).
 */
beforeEach(function () {
    config(['services.kadi_api.url' => 'https://api.kadi.example/api/v1/', 'services.kadi_api.key' => 'site-key']);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    $this->travelTo(Date::parse('2026-11-13T14:37:20+03:00'));
    Cache::forever(MarketFeed::OK_AT_KEY, Date::now()->getTimestamp());
    $this->player = User::factory()->create(['linked_id' => 501]);
});

function myBet(int $id, array $changes = []): array
{
    return array_replace(predictFixture('bets-list')['data']['items'][0], ['id' => $id, 'reference' => "secret-ref-{$id}"], $changes);
}

/** @param  array<string, list<array<string, mixed>>|array{0: list<array<string, mixed>>, 1: int}>  $byStatus */
function fakeMyBets(array $byStatus, int $lastPage = 1): void
{
    Http::fake(function (Request $request) use ($byStatus, $lastPage) {
        if (! str_contains($request->url(), '/predict/bets')) {
            return Http::response([], 404);
        }

        $status = $request->data()['status'] ?? 'open';
        $page = (int) ($request->data()['page'] ?? 1);
        $items = $byStatus["{$status}:{$page}"] ?? $byStatus[$status] ?? [];

        return Http::response(['success' => true, 'data' => ['items' => $items, 'pagination' => ['page' => $page, 'per_page' => 20, 'total' => 25, 'last_page' => $lastPage]]]);
    });
}

test('guests are sent to sign in', function () {
    $this->get('/predictions/bets')->assertRedirect(route('login'));
});

test('the page is not caught by the market route', function () {
    fakeMyBets(['open' => [myBet(1)]]);

    $this->actingAs($this->player)->get('/predictions/bets')->assertOk()->assertSee('My bets');
});

test('open bets show the payout and whether the odds are fixed or estimated', function () {
    fakeMyBets(['open' => [
        myBet(1, ['is_estimate' => true, 'multiplier' => 1.76, 'potential_payout' => 176]),
        myBet(2, ['is_estimate' => false, 'multiplier' => 1.58, 'potential_payout' => 158, 'side' => 'no']),
    ]]);

    Livewire::actingAs($this->player)->test(MyBets::class)
        ->assertSee('KES 176.00')->assertSee('1.76x · Estimated')
        ->assertSee('KES 158.00')->assertSee('1.58x · Fixed odds')
        ->assertDontSee('secret-ref-1');

    Http::assertSent(fn (Request $request) => $request->data()['status'] === 'open');
});

test('settled bets read won, lost or stake refunded, from the API\'s settled filter', function () {
    fakeMyBets(['settled' => [
        myBet(1, ['status' => 'won', 'payout' => 176, 'multiplier' => 1.76]),
        myBet(2, ['status' => 'lost', 'payout' => 0]),
        myBet(3, ['status' => 'void', 'payout' => 100]),
    ]]);

    Livewire::actingAs($this->player)->test(MyBets::class)->call('setTab', 'settled')
        ->assertSee('Won KES 176.00')
        ->assertSee('Lost')
        ->assertSee('Stake refunded');

    Http::assertSent(fn (Request $request) => $request->data()['status'] === 'settled');
});

test('the market\'s own labels are used when the site has the market cached, without asking the API', function () {
    Cache::put('predict.market.12.fresh', ['market' => ['id' => 12, 'odds' => ['yes_label' => 'Arsenal', 'no_label' => 'Not Arsenal']], 'fetched_at' => 0], 60);
    fakeMyBets(['open' => [myBet(1), myBet(2, ['market_id' => 99])]]);

    Livewire::actingAs($this->player)->test(MyBets::class)->assertSee('Arsenal')->assertSee('Yes');

    Http::assertSentCount(1);
});

test('bets are cached briefly, and a placement makes the next read ask again', function () {
    fakeMyBets(['open' => [myBet(1)]]);

    Livewire::actingAs($this->player)->test(MyBets::class);
    Livewire::actingAs($this->player)->test(MyBets::class);
    Http::assertSentCount(1);

    MyBetsCache::bump($this->player);
    Livewire::actingAs($this->player)->test(MyBets::class);
    Http::assertSentCount(2);
});

test('load more adds the next page, and an outage shows the last good copy', function () {
    fakeMyBets(['open:1' => [myBet(1), myBet(2)], 'open:2' => [myBet(3)]], lastPage: 2);

    $page = Livewire::actingAs($this->player)->test(MyBets::class)
        ->assertSee('Showing 2 of 25')
        ->assertSeeHtml('data-predict-load-more')
        ->call('loadMore')
        ->assertSee('Showing 3 of 25')
        ->assertDontSeeHtml('data-predict-load-more');

    Http::fake(['*' => Http::response([], 500)]);
    MyBetsCache::bump($this->player);
    Livewire::actingAs($this->player)->test(MyBets::class)->assertSee('Showing 2 of 25');
});

test('with nothing to show, an outage says so', function () {
    Http::fake(['*' => Http::response([], 500)]);

    Livewire::actingAs($this->player)->test(MyBets::class)->assertSee('be loaded right now');
});

test('a bet still being checked shows at the top with "Try again"', function () {
    PendingAttempt::put($this->player, ['reference' => 'ref-zz9q-secret', 'market_id' => 12, 'side' => 'yes', 'stake' => 100.0, 'quote_id' => null]);
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/predict/markets/12')) {
            return Http::response(['success' => true, 'data' => array_replace(predictFixture('market-polymarket-pool')['body']['data'], ['id' => 12, 'betting_closes_at' => '2026-12-30T23:45:00+03:00'])]);
        }

        return Http::response(['success' => true, 'data' => ['items' => [], 'pagination' => ['page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1]]]);
    });

    $this->actingAs($this->player)->get('/predictions/bets')
        ->assertSee('Checking this bet')
        ->assertSee('checking your bet')
        ->assertSee('Try again')
        ->assertDontSee('ref-zz9q-secret');
});

test('My bets is in the predictions menu, and asks guests to sign in', function () {
    $guest = collect((new SiteNavigation(null))->groups())->firstWhere('key', 'predictions')['items'];
    $player = collect((new SiteNavigation($this->player))->groups())->firstWhere('key', 'predictions')['items'];

    expect(collect($guest)->firstWhere('label', 'My bets'))->toMatchArray(['needsAccount' => true])
        ->and(collect($player)->firstWhere('label', 'My bets'))->toMatchArray(['needsAccount' => false]);
});
