<?php

use App\Games\HeroSlides;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Support\Money;
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

    $this->slots = ['slots_dragon_fire_fortune', 'slots_crown_of_embers', 'slots_berry_blast'];

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

test('the lead slide is always first, even with every other gate off', function () {
    config([
        'platform.token_launch' => false,
        'platform.sports' => false,
        'kadi.promotions.enabled' => false,
        'kadi.referrals.enabled' => false,
        'hero.slides' => array_values(array_filter(config('hero.slides'), fn ($s) => $s['kind'] !== 'install')),
    ]);

    expect(heroKeys())->toBe(['lead']);
});

test('slides follow their switches', function (string $switch, string $slide) {
    expect(heroKeys())->toContain($slide);

    config([$switch => false]);

    expect(heroKeys())->not->toContain($slide)->and(heroKeys()[0])->toBe('lead');
})->with([
    'welcome bonus' => ['kadi.promotions.enabled', 'welcome'],
    'invite & earn' => ['kadi.referrals.enabled', 'invite'],
    'slots' => ['platform.token_launch', 'slots'],
]);

test('a games slide lists only titles the catalogue offers', function () {
    $this->slots = ['slots_dragon_fire_fortune', 'slots_berry_blast'];

    $slide = app(HeroSlides::class)->forLanding()->firstWhere('key', 'slots');

    expect(collect($slide->extra['titles'])->pluck('code')->all())->toBe(['slots_dragon_fire_fortune', 'slots_berry_blast'])
        ->and(collect($slide->art)->pluck('role')->unique()->all())->toBe(['card']);
});

test('a games slide with none of its titles in the catalogue is dropped', function () {
    $this->slots = ['slots_candy_kingdom'];

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

test('never more than max_slides, the lead first', function () {
    $extra = array_map(fn (int $i) => ['key' => "extra-{$i}", 'kind' => 'promo', 'label' => "Extra {$i}", 'title' => "Extra {$i}"], range(1, 5));
    config(['hero.slides' => [...config('hero.slides'), ...$extra], 'hero.max_slides' => 6]);

    $keys = heroKeys();

    expect($keys)->toHaveCount(6)->and($keys[0])->toBe('lead');
});

test('the welcome title carries the configured amount', function () {
    $slide = app(HeroSlides::class)->forLanding()->firstWhere('key', 'welcome');

    expect($slide->title)->toBe('KES 20 welcome bonus');
});

test('slides take their durations from config', function () {
    $slides = app(HeroSlides::class)->forLanding();

    expect($slides->first()->duration)->toBe(config('hero.durations.lead'))
        ->and($slides->firstWhere('key', 'welcome')->duration)->toBe(config('hero.durations.default'));
});
