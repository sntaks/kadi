<?php

use App\Games\HeroSlides;
use App\Predict\Markets;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Support\Money;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

/*
 * The landing hero (plans/landing-hero-slider-implementation-plan.md): slides from config/hero.php, kept only while
 * what they advertise is live.
 */

const HERO_API = 'https://kadi-api.test/api/v1/';

/** @param  list<string>  $slots  title keys; the API's codes carry the `slots_` prefix */
function heroCatalogue(array $slots, array $casino = []): array
{
    $extra = collect($casino)->map(fn (array $codes, string $game) => [
        'code' => $game, 'name' => ucfirst($game), 'launch_url' => "https://{$game}.kadi.online", 'status' => 'active', 'sort_order' => 3,
        'products' => array_map(fn (string $code) => ['code' => $code, 'name' => ucwords(str_replace('_', ' ', $code)), 'status' => 'active'], $codes),
    ])->values()->all();

    return ['data' => [...$extra,
        ['code' => 'kadi', 'name' => 'Kadi', 'launch_url' => 'https://play.kadi.online', 'status' => 'active', 'sort_order' => 1, 'products' => [
            ['code' => 'kadi', 'name' => 'Kadi', 'status' => 'active'],
        ]],
        ['code' => 'slots', 'name' => 'Slots', 'launch_url' => 'https://slots.kadi.online', 'status' => 'active', 'sort_order' => 2, 'products' => array_map(
            fn (string $key) => ['code' => 'slots_'.$key, 'name' => ucwords(str_replace('_', ' ', $key)), 'status' => 'active'],
            $slots,
        )],
    ]];
}

/**
 * A Local trending list for the market slides: one open market per [id, category, event].
 *
 * @param  list<array{0: int, 1: string, 2?: string|null}>  $markets
 */
function heroMarketList(array $markets): array
{
    $sample = predictFixture('markets-list')['data']['items'][0];
    $items = array_map(fn (array $m) => [...$sample, 'id' => $m[0], 'category' => $m[1], 'question' => "Market {$m[0]}?", 'event' => isset($m[2]) ? ['title' => $m[2]] : null], $markets);

    return ['success' => true, 'data' => ['items' => $items, 'pagination' => ['page' => 1, 'per_page' => 20, 'total' => count($items), 'last_page' => 1]]];
}

beforeEach(function () {
    config([
        'services.kadi_api.url' => HERO_API,
        'services.kadi_api.key' => 'site-key',
        'platform.token_launch' => true,
        'platform.deep_link' => true,
        'platform.sports' => true,
        'kadi.promotions.enabled' => true,
        'kadi.promotions.signup_bonus_amount' => 20,
        'kadi.referrals.enabled' => true,
        'hero.max_slides' => 6,
    ]);

    $this->slots = ['pharaohs_fortune', 'crown_of_embers', 'berry_blast'];
    $this->casino = [];
    $this->marketList = heroMarketList([]);

    Http::preventStrayRequests();
    Http::fake(function ($request) {
        return match (true) {
            $request->url() === HERO_API.'games' => Http::response(heroCatalogue($this->slots, $this->casino)),
            str_starts_with($request->url(), HERO_API.'predict/markets') => Http::response($this->marketList),
            default => Http::response([], 200),
        };
    });
});

function heroKeys(): array
{
    return app(HeroSlides::class)->forLanding()->pluck('key')->all();
}

test('with every other gate off only the Kadi slide is left', function () {
    config([
        'platform.token_launch' => false,
        'platform.sports' => false,
        'kadi.promotions.enabled' => false,
        'kadi.referrals.enabled' => false,
    ]);

    expect(heroKeys())->toBe(['kadi']);
});

test('slides follow their switches', function (string $switch, string $slide) {
    expect(heroKeys())->toContain($slide);

    config([$switch => false]);

    expect(heroKeys())->not->toContain($slide);
})->with([
    'invite & earn' => ['kadi.referrals.enabled', 'invite'],
    'slots' => ['platform.token_launch', 'slots'],
    'slot jackpots' => ['platform.token_launch', 'slot-jackpots'],
]);

test('the welcome bonus and install slides are gone', function () {
    expect(collect(config('hero.slides'))->pluck('key'))->not->toContain('welcome')->not->toContain('install');
});

test('a games slide lists only titles the catalogue offers', function () {
    $this->slots = ['pharaohs_fortune', 'berry_blast'];

    $slide = app(HeroSlides::class)->forLanding()->firstWhere('key', 'slots');

    expect(collect($slide->extra['titles'])->pluck('code')->all())->toBe(['slots_pharaohs_fortune', 'slots_berry_blast'])
        ->and(collect($slide->art)->pluck('role')->unique()->all())->toBe(['card']);
});

test('a games slide with none of its titles in the catalogue is dropped', function () {
    $this->slots = ['candy_kingdom'];

    expect(heroKeys())->not->toContain('slots');
});

test('the jackpot slide needs a jackpot taking entries, with its real prize', function () {
    expect(heroKeys())->not->toContain('jackpot');

    $jackpot = openJackpot();
    $slide = app(HeroSlides::class)->forLanding()->firstWhere('key', 'jackpot');

    expect($slide)->not->toBeNull()
        ->and($slide->title)->toContain(Money::format($jackpot->totalPrizes()))
        ->and($slide->extra['prize'])->toBe($jackpot->totalPrizes());

    $jackpot->update(['status' => JackpotStatus::Settled]);

    expect(heroKeys())->not->toContain('jackpot');
});

test('the jackpot slide stays away while sports is off', function () {
    openJackpot();
    config(['platform.sports' => false]);

    expect(heroKeys())->not->toContain('jackpot');
});

test('a slide whose CTA route does not exist is dropped', function () {
    config(['hero.slides' => [
        ...config('hero.slides'),
        ['key' => 'ghost', 'kind' => 'promo', 'label' => 'Ghost', 'title' => 'Boo', 'cta' => ['label' => 'Go', 'route' => 'nope']],
    ]]);

    expect(heroKeys())->not->toContain('ghost');
});

test('an unknown gate fails closed', function () {
    config(['hero.slides' => [
        ...config('hero.slides'),
        ['key' => 'odd', 'kind' => 'promo', 'label' => 'Odd', 'title' => 'Odd', 'requires' => ['made_up']],
    ]]);

    expect(heroKeys())->not->toContain('odd');
});

test('never more than max_slides', function () {
    $extra = array_map(fn (int $i) => ['key' => "extra-{$i}", 'kind' => 'promo', 'label' => "Extra {$i}", 'title' => "Extra {$i}"], range(1, 5));
    config(['hero.slides' => [...config('hero.slides'), ...$extra], 'hero.max_slides' => 6]);

    expect(heroKeys())->toHaveCount(6)
        ->and(app(HeroSlides::class)->forLanding(seed: 7)->pluck('key')->all())->toHaveCount(6);
});

test('a seed shuffles the live slides, the same way every time for the same seed', function () {
    config(['hero.max_slides' => 20]);
    $live = heroKeys();
    $orders = collect(range(1, 40))->map(fn (int $seed) => app(HeroSlides::class)->forLanding(seed: $seed)->pluck('key')->all());

    expect(app(HeroSlides::class)->forLanding(seed: 5)->pluck('key')->all())->toBe(app(HeroSlides::class)->forLanding(seed: 5)->pluck('key')->all())
        ->and($orders->every(fn (array $keys) => collect($keys)->sort()->values()->all() === collect($live)->sort()->values()->all()))->toBeTrue()
        ->and($orders->map(fn (array $keys) => $keys[0])->unique()->count())->toBeGreaterThan(1);
});

test('the Kadi slide can be left out when there are more live slides than room', function () {
    $extra = array_map(fn (int $i) => ['key' => "extra-{$i}", 'kind' => 'promo', 'label' => "Extra {$i}", 'title' => "Extra {$i}"], range(1, 10));
    config(['hero.slides' => [...config('hero.slides'), ...$extra], 'hero.max_slides' => 3]);

    $withKadi = collect(range(1, 40))->filter(fn (int $seed) => app(HeroSlides::class)->forLanding(seed: $seed)->pluck('key')->contains('kadi'));

    expect($withKadi->count())->toBeGreaterThan(0)->toBeLessThan(40);
});

test('the big match is always kept and never the first slide', function () {
    $extra = array_map(fn (int $i) => ['key' => "extra-{$i}", 'kind' => 'promo', 'label' => "Extra {$i}", 'title' => "Extra {$i}"], range(1, 10));
    config(['hero.slides' => [...config('hero.slides'), ...$extra], 'hero.max_slides' => 4]);

    foreach (range(1, 40) as $seed) {
        $keys = app(HeroSlides::class)->forLanding(bigMatch: true, seed: $seed)->pluck('key');

        expect($keys->first())->not->toBe('football')->and($keys)->toContain('football');
    }
});

test('casino slides stay away while the catalogue does not offer their game', function () {
    expect(heroKeys())->not->toContain('scratch')->not->toContain('coinflip')->not->toContain('roulette');
});

test('casino slides carry the configured figures and their titles', function () {
    $this->casino = ['scratch' => ['scratch_dragon', 'scratch_fruit'], 'coinflip' => ['coinflip_gold_rush'], 'roulette' => ['roulette_classic_black']];
    $slides = app(HeroSlides::class)->forLanding()->keyBy('key');

    expect($slides['scratch']->title)->toBe('KSh 1,000,000 is up for grabs')
        ->and(collect($slides['scratch']->extra['titles'])->pluck('code')->all())->toBe(['scratch_dragon', 'scratch_fruit'])
        ->and($slides['coinflip']->copy)->toContain('up to 30x your stake')
        ->and($slides['coinflip']->ctaUrl)->toBe(route('casino', ['filter' => 'coin-flip']))
        ->and($slides['roulette']->copy)->toContain('up to 35x your stake')
        ->and($slides['roulette']->ctaUrl)->toBe(route('casino', ['filter' => 'roulette']));
});

test('a casino slide whose figure is not configured is dropped, never shown without it', function () {
    $this->casino = ['coinflip' => ['coinflip_gold_rush']];
    config(['games.games.coinflip.max_multiplier' => null]);

    expect(heroKeys())->not->toContain('coinflip');
});

test('the slot jackpot slide lists the configured tiers while slots are offered', function () {
    $slide = app(HeroSlides::class)->forLanding()->firstWhere('key', 'slot-jackpots');

    expect(collect($slide->extra['tiers'])->map(fn (array $t) => [$t['name'], $t['amount']])->all())
        ->toBe([['Gold', 2_000_000], ['Silver', 300_000], ['Bronze', 60_000], ['Iron', 20_000]]);
});

test('the slot jackpot slide stays away while no slots are offered', function () {
    $this->slots = [];

    expect(heroKeys())->not->toContain('slot-jackpots');
});

describe('market slides', function () {
    beforeEach(fn () => $this->travelTo(Date::parse('2026-11-15 09:00:00', 'UTC')));

    test('read the cache only: with nothing cached there are none, and the API is not asked', function () {
        expect(heroKeys())->not->toContain('market-1')->not->toContain('market-2');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'predict/'));
    });

    test('show the trending Local markets, politics first, one per event', function () {
        $this->marketList = heroMarketList([[1, 'sport'], [2, 'politics', 'Election'], [3, 'politics', 'Election'], [4, 'politics']]);
        app(Markets::class)->warmLobby();

        $slides = app(HeroSlides::class)->forLanding()->keyBy('key');

        expect($slides['market-1']->extra['market']->id)->toBe(2)
            ->and($slides['market-1']->title)->toBe('Election')
            ->and($slides['market-2']->extra['market']->id)->toBe(4)
            ->and($slides['market-1']->ctaUrl)->toBe(route('predictions', ['source' => 'local']));
    });

    test('pinned markets come first', function () {
        $this->marketList = heroMarketList([[1, 'sport'], [2, 'politics'], [3, 'politics']]);
        app(Markets::class)->warmLobby();
        config(['hero.markets' => [3]]);

        expect(collect(app(HeroSlides::class)->markets())->pluck('id')->all())->toBe([3, 2]);
    });

    test('a market that is one choice of an event shows the event and every choice, likeliest first', function () {
        $this->marketList = heroMarketList([[10, 'politics', 'Who wins?'], [11, 'politics', 'Who wins?'], [12, 'politics', 'Who wins?']]);
        foreach ([[10, 0.2], [11, 0.5], [12, 0.3]] as $i => [$id, $p]) {
            $this->marketList['data']['items'][$i]['odds']['yes_probability'] = $p;
        }
        app(Markets::class)->warmLobby();
        config(['hero.markets' => [10]]);

        $slide = app(HeroSlides::class)->forLanding()->firstWhere('key', 'market-1');

        expect($slide->title)->toBe('Who wins?')
            ->and(collect($slide->extra['choices'])->pluck('id')->all())->toBe([11, 12, 10]);
    });

    test('the event slide renders every choice on the landing', function () {
        $this->marketList = heroMarketList([[10, 'politics', 'Who wins?'], [11, 'politics', 'Who wins?'], [12, 'politics', 'Who wins?']]);
        app(Markets::class)->warmLobby();
        config(['hero.markets' => [10]]);

        $html = $this->get('/')->assertOk()->getContent();

        expect($html)->toContain('nt-hero-choices')->toContain('3 choices')
            ->toContain('data-predict-market="10"')->toContain('data-predict-market="11"')->toContain('data-predict-market="12"');
    });

    test('closed markets are never shown', function () {
        $this->marketList = heroMarketList([[1, 'politics']]);
        $this->marketList['data']['items'][0]['status'] = 'resolved';
        app(Markets::class)->warmLobby();

        expect(app(HeroSlides::class)->markets())->toBe([]);
    });

    test('stay away while predictions are off', function () {
        $this->marketList = heroMarketList([[1, 'politics'], [2, 'politics']]);
        app(Markets::class)->warmLobby();
        config(['platform.predictions' => false]);

        expect(heroKeys())->not->toContain('market-1');
    });

    test('render the live market card on the landing', function () {
        $this->marketList = heroMarketList([[41, 'politics'], [42, 'politics']]);
        app(Markets::class)->warmLobby();
        config(['hero.max_slides' => 20]);

        $html = $this->get('/')->assertOk()->getContent();

        expect($html)->toContain('data-slide="market-1"')->toContain('data-predict-market="41"')->toContain('Market 42?');
    });
});

test('slides take their durations from config', function () {
    $slides = app(HeroSlides::class)->forLanding();

    expect($slides->first()->duration)->toBe(config('hero.slides.0.duration'))
        ->and($slides->firstWhere('key', 'invite')->duration)->toBe(config('hero.durations.default'));
});

test('every hero image the config uses has been built', function () {
    $missing = [];

    foreach (HeroSlides::sources() as $source => $role) {
        foreach (HeroSlides::widths($role) as $width) {
            $built = public_path(HeroSlides::builtPath($source, $width));

            if (! is_file($built) || filesize($built) > 90 * 1024) {
                $missing[] = HeroSlides::builtPath($source, $width);
            }
        }
    }

    expect($missing)->toBe([]);
});

// ─── On the landing ──────────────────────────────────────────────────────────

test('the landing shows the Kadi table and the big match, each its own slide, under one page h1', function () {
    $this->travelTo(Date::parse('2026-11-07 06:00:00', 'UTC'));
    pricedFixture(['home_team' => 'Liverpool', 'away_team' => 'Everton', 'kicks_off_at' => Date::parse('2026-11-07 15:00:00', 'UTC')]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toMatch('/data-slide="kadi".*kadi-table-title/s')->toMatch('/data-slide="football".*bigmatch-title/s')
        ->and(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('<h1 class="sr-only">');
});

test('the landing opens on a different slide from visit to visit, but never on the big match', function () {
    $this->travelTo(Date::parse('2026-11-07 06:00:00', 'UTC'));
    pricedFixture(['kicks_off_at' => Date::parse('2026-11-07 15:00:00', 'UTC')]);

    $firsts = collect(range(1, 15))->map(function () {
        preg_match('/<article[^>]*data-slide="([^"]+)"/', $this->get('/')->getContent(), $m);

        return $m[1];
    });

    expect($firsts->unique()->count())->toBeGreaterThan(1)->and($firsts)->not->toContain('football');
});

test('without a big match there is no football slide', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('data-slide="kadi"')->and($html)->toContain('kadi-table-title')
        ->and($html)->not->toContain('data-slide="football"')->and($html)->not->toContain('bigmatch-title');
});

test('every slide but the first is hidden and inert, and survives a Livewire re-render', function () {
    $html = $this->get('/')->assertOk()->getContent();

    preg_match_all('/<article[^>]*data-slide="([^"]+)"[^>]*>/', $html, $tags);

    expect($tags[1])->toHaveCount(app(HeroSlides::class)->forLanding()->count())->and(count($tags[1]))->toBeGreaterThan(1);

    foreach ($tags[0] as $i => $tag) {
        expect($tag)->toContain('wire:ignore.self');
        if ($i === 0) {
            expect($tag)->toContain('data-state="active"')->not->toContain(' hidden');
        } else {
            expect($tag)->toContain('data-state="idle"');
            // The big match shows under the carousel on phones, so it must stay visible and usable there: hero.css
            // hides it from 768px instead of the hidden attribute.
            str_contains($tag, 'data-below')
                ? expect($tag)->not->toContain(' hidden')->not->toContain(' inert')
                : expect($tag)->toContain(' hidden')->toContain(' inert');
        }
    }
});

test('one slide shows no carousel controls', function () {
    config([
        'platform.token_launch' => false, 'platform.sports' => false, 'kadi.promotions.enabled' => false, 'kadi.referrals.enabled' => false,
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('data-count="1"')->and($html)->not->toContain('data-hero-clock');
});

test('the hero has no visible controls at any width, only the hidden autoplay clock', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toMatch('/<i[^>]*data-hero-clock[^>]*aria-hidden="true"/')
        ->and($html)->not->toContain('data-hero-chrome')
        ->and($html)->not->toContain('data-hero-next')
        ->and($html)->not->toContain('data-hero-prev')
        ->and($html)->not->toContain('data-hero-toggle')
        ->and($html)->not->toContain('data-hero-go');
});

test('the Kadi slide shows the published jackpot prizes, the same figures as the games guide', function () {
    config(['games.games.slots.jackpots' => []]);
    $gold = config('games.games.kadi.jackpots.gold');
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('Fortune Favors the Bold')
        ->and($html)->toContain('data-prize-pool')
        ->and($html)->toContain('Gold jackpot')
        ->and(substr_count($html, 'class="nt-prize-medal"'))->toBe(4)
        ->and($html)->toContain('data-tone="gold"')->and($html)->toContain('data-tone="silver"')
        ->and($html)->toContain('data-tone="bronze"')->and($html)->toContain('data-tone="blue"')
        ->and($html)->not->toContain('class="nt-hand');

    foreach ($gold['prizes'] as [, $amount]) {
        expect($html)->toContain('KSh '.number_format($amount));
    }

    $guide = $this->get(route('game-guide'))->assertOk()->getContent();

    foreach ($gold['prizes'] as [, $amount]) {
        expect($guide)->toContain('KSh '.number_format($amount));
    }
});

test('without a configured jackpot the Kadi slide shows no prize pool', function () {
    config(['games.games.kadi.hero_jackpot' => null, 'games.games.slots.jackpots' => []]);

    expect($this->get('/')->assertOk()->getContent())->not->toContain('data-prize-pool');
});

test('slide figures come from the server: the jackpot prize, never player counts', function () {
    $jackpot = openJackpot();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain(e(Money::format($jackpot->totalPrizes())));
});

test('hero images wait for their slide, except the slide the page opens on', function () {
    $this->casino = ['scratch' => ['scratch_dragon', 'scratch_fruit']];
    $html = $this->get('/')->assertOk()->getContent();

    preg_match_all('/<article[^>]*data-slide="([^"]+)"[^>]*>(.*?)<\/article>/s', $html, $slides, PREG_SET_ORDER);
    $lazy = 0;

    foreach ($slides as $i => [, $key, $body]) {
        preg_match_all('/<img[^>]*>/s', $body, $images);

        foreach ($images[0] as $img) {
            if ($i === 0) {
                // The opening slide (shuffled: any slide) shows its art without waiting for the script.
                expect($img)->not->toContain('data-src')->and($img)->toMatch('/ srcset="[^"]+"/')->and($img)->not->toContain('data:image/gif');
            } elseif (str_contains($img, 'data-srcset')) {
                $lazy++;
                expect($img)->toMatch('/ src="data:image\/gif;base64,[^"]+"/')->and($img)->not->toMatch('/ srcset="/');
            }
        }
    }

    expect($lazy)->toBeGreaterThan(0)->and($html)->not->toContain('<source');
});

test('an opening slide renders its real images, any other slide the placeholder', function () {
    $slide = app(HeroSlides::class)->forLanding()->firstWhere('key', 'invite');

    $eager = (string) $this->blade('<x-lobby.hero-slide :slide="$slide" :eager="true" />', ['slide' => $slide]);
    $lazy = (string) $this->blade('<x-lobby.hero-slide :slide="$slide" />', ['slide' => $slide]);

    expect($eager)->toContain('src="'.$slide->art[0]['src'].'"')->not->toContain('data-src')
        ->and($lazy)->toContain('data-src="'.$slide->art[0]['src'].'"')->toContain('src="data:image/gif');
});

test('slide images are kept out of Livewire re-renders, so a loaded image never goes back to the placeholder', function () {
    $html = $this->get('/')->assertOk()->getContent();

    preg_match_all('/<div class="nt-hero-(?:bg|fan|object)"[^>]*>/', $html, $layers);

    expect($layers[0])->not->toBeEmpty();

    foreach ($layers[0] as $layer) {
        expect($layer)->toContain('wire:ignore');
    }
});

test('a games slide links each card to its game page', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('href="'.url('/games/pharaohs-fortune').'"');
});

test('the slider script survives a Livewire re-render', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toMatch('/<section[^>]*data-hero[^>]*x-data="heroSlider"[^>]*wire:ignore\.self/s');
});

test('the slot jackpot slide shows each tier with its own colour and icon', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('nt-hero-tiers')->toContain('data-tone="iron"')
        ->toContain('KSh 2,000,000')->toContain('KSh 300,000')->toContain('KSh 60,000')->toContain('KSh 20,000');
});

test('the football slide follows the big match', function () {
    expect(app(HeroSlides::class)->forLanding(bigMatch: true)->pluck('key')->all())->toContain('football')
        ->and(heroKeys())->not->toContain('football');

    config(['platform.sports' => false]);

    expect(app(HeroSlides::class)->forLanding(bigMatch: true)->pluck('key'))->not->toContain('football');
});

test('on phones the big match stays below the carousel, outside the rotation', function () {
    $this->travelTo(Date::parse('2026-11-07 06:00:00', 'UTC'));
    pricedFixture(['kicks_off_at' => Date::parse('2026-11-07 15:00:00', 'UTC')]);

    preg_match_all('/<article[^>]*data-slide="([^"]+)"[^>]*data-below/', $this->get('/')->assertOk()->getContent(), $below);

    expect($below[1])->toBe(['football']);
});
