<?php

use App\Games\HeroSlides;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Support\Money;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

/*
 * The landing hero (plans/landing-hero-slider-implementation-plan.md): slides from config/hero.php, kept only while
 * what they advertise is live.
 */

const HERO_API = 'https://kadi-api.test/api/v1/';

/** @param  list<string>  $slots */
function heroCatalogue(array $slots): array
{
    return ['data' => [
        ['code' => 'kadi', 'name' => 'Kadi', 'launch_url' => 'https://play.kadi.online', 'status' => 'active', 'sort_order' => 1, 'products' => [
            ['code' => 'kadi', 'name' => 'Kadi', 'status' => 'active'],
        ]],
        ['code' => 'slots', 'name' => 'Slots', 'launch_url' => 'https://slots.kadi.online', 'status' => 'active', 'sort_order' => 2, 'products' => array_map(
            fn (string $code) => ['code' => $code, 'name' => ucwords(str_replace('_', ' ', substr($code, 6))), 'status' => 'active'],
            $slots,
        )],
    ]];
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
    ]);

    $this->slots = ['dragon_fire_fortune', 'crown_of_embers', 'berry_blast'];

    Http::preventStrayRequests();
    Http::fake(function ($request) {
        return match (true) {
            $request->url() === HERO_API.'games' => Http::response(heroCatalogue($this->slots)),
            default => Http::response([], 200),
        };
    });
});

function heroKeys(): array
{
    return app(HeroSlides::class)->forLanding()->pluck('key')->all();
}

test('the Kadi slide is always first, even with every other gate off', function () {
    config([
        'platform.token_launch' => false,
        'platform.sports' => false,
        'kadi.promotions.enabled' => false,
        'kadi.referrals.enabled' => false,
        'hero.slides' => array_values(array_filter(config('hero.slides'), fn ($s) => $s['kind'] !== 'install')),
    ]);

    expect(heroKeys())->toBe(['kadi']);
});

test('slides follow their switches', function (string $switch, string $slide) {
    expect(heroKeys())->toContain($slide);

    config([$switch => false]);

    expect(heroKeys())->not->toContain($slide)->and(heroKeys()[0])->toBe('kadi');
})->with([
    'welcome bonus' => ['kadi.promotions.enabled', 'welcome'],
    'invite & earn' => ['kadi.referrals.enabled', 'invite'],
    'slots' => ['platform.token_launch', 'slots'],
]);

test('a games slide lists only titles the catalogue offers', function () {
    $this->slots = ['dragon_fire_fortune', 'berry_blast'];

    $slide = app(HeroSlides::class)->forLanding()->firstWhere('key', 'slots');

    expect(collect($slide->extra['titles'])->pluck('code')->all())->toBe(['dragon_fire_fortune', 'berry_blast'])
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

test('never more than max_slides, Kadi first', function () {
    $extra = array_map(fn (int $i) => ['key' => "extra-{$i}", 'kind' => 'promo', 'label' => "Extra {$i}", 'title' => "Extra {$i}"], range(1, 5));
    config(['hero.slides' => [...config('hero.slides'), ...$extra], 'hero.max_slides' => 6]);

    $keys = heroKeys();

    expect($keys)->toHaveCount(6)->and($keys[0])->toBe('kadi');
});

test('the welcome title carries the configured amount', function () {
    $slide = app(HeroSlides::class)->forLanding()->firstWhere('key', 'welcome');

    expect($slide->title)->toBe('KES 20 welcome bonus');
});

test('slides take their durations from config', function () {
    $slides = app(HeroSlides::class)->forLanding();

    expect($slides->first()->duration)->toBe(config('hero.slides.0.duration'))
        ->and($slides->firstWhere('key', 'welcome')->duration)->toBe(config('hero.durations.default'));
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

test('the landing opens on the Kadi table, then the big match, each its own slide', function () {
    $this->travelTo(Date::parse('2026-11-07 06:00:00', 'UTC'));
    pricedFixture(['home_team' => 'Liverpool', 'away_team' => 'Everton', 'kicks_off_at' => Date::parse('2026-11-07 15:00:00', 'UTC')]);

    $this->get('/')->assertOk()->assertSeeInOrder(['data-hero', 'data-slide="kadi"', 'kadi-table-title', 'data-slide="football"', 'bigmatch-title', 'data-slide="welcome"'], false);
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
        'hero.slides' => array_values(array_filter(config('hero.slides'), fn ($s) => $s['kind'] !== 'install')),
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
    config(['games.games.kadi.hero_jackpot' => null]);

    expect($this->get('/')->assertOk()->getContent())->not->toContain('data-prize-pool');
});

test('slide figures come from the server: the jackpot prize, never player counts', function () {
    $jackpot = openJackpot();

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain(e(Money::format($jackpot->totalPrizes())));
});

test('hero images wait for their slide: nothing is requested until the slider asks', function () {
    $html = $this->get('/')->assertOk()->getContent();

    preg_match_all('/<img[^>]*data-srcset[^>]*>/s', $html, $images);

    expect($images[0])->not->toBeEmpty()->and($html)->not->toContain('<source');

    foreach ($images[0] as $img) {
        expect($img)->toMatch('/ src="data:image\/gif;base64,[^"]+"/')
            ->and($img)->toContain('data-src="')
            ->and($img)->not->toMatch('/ srcset="/');
    }
});

test('a games slide links each card to its game page', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('href="'.url('/games/dragon-fire-fortune').'"');
});

test('the slider script survives a Livewire re-render', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toMatch('/<section[^>]*data-hero[^>]*x-data="heroSlider"[^>]*wire:ignore\.self/s');
});

test('the football slide follows the big match', function () {
    expect(app(HeroSlides::class)->forLanding(bigMatch: true)->pluck('key')->take(2)->all())->toBe(['kadi', 'football'])
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
