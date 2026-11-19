<?php

/*
|--------------------------------------------------------------------------
| Landing hero slider
|--------------------------------------------------------------------------
|
| The slides of the landing's hero carousel. Only App\Games\HeroSlides reads
| this: it drops any slide whose `requires` gates are off, whose CTA route does
| not exist, whose games the catalogue does not offer, (jackpot) while no jackpot
| takes entries or (market) with no market to show. Every page load shuffles the
| live slides and shows `max_slides` of them; the big match is never the first.
|
| Copy only: never put a figure here that the site cannot back. `:amount` is the
| signup bonus (kadi.promotions.signup_bonus_amount), `:prize` the open jackpot's
| real prize total (jackpot) or games.games.{game}.hero_prize (casino),
| `:closes` its closing time, `:multiplier` games.games.{game}.max_multiplier,
| `:question` the market's question (market).
|
| Kinds: kadi, match, games (`titles`), jackpot, promo, casino (`game`: a fan of
| that game's titles), tiers (games.games.{game}.jackpots as a prize table) and
| market (`slot` 0 or 1 of HeroSlides::markets(): the `markets` ids, else the
| trending Local markets, politics first; read from the cache only).
|
| `art[].from` is an image under public/; `php artisan hero:artwork` builds the
| sizes the page uses into public/images/hero/. Commit them.
|
| `requires`: promotions, referrals, sports, token_launch, predict. `cta.account`: the CTA is
| an account-only page (guests get the sign-in sheet, like every such link);
| `cta.params`: route parameters.
| `transition`: curtain, iris, slices, parallax, fade-zoom.
|
*/

return [
    'max_slides' => 6,

    'durations' => ['default' => 7000], // ms; a slide's own `duration` wins

    // Market ids staff pin to the two market slides (HERO_PREDICT_MARKETS=12,34). Unset, closed or not in the cache:
    // the trending Local markets fill in, politics first.
    'markets' => array_values(array_filter(array_map('intval', explode(',', (string) env('HERO_PREDICT_MARKETS', ''))))),

    // The categories the market slides prefer, in order; any other Local market comes after.
    'market_categories' => ['politics'],

    'slides' => [
        [
            'key' => 'kadi', 'kind' => 'kadi', 'label' => 'Kadi', 'title' => 'Fortune Favors the Bold',
            'transition' => 'curtain', 'duration' => 8000,
        ],
        [
            'key' => 'football', 'kind' => 'match', 'label' => 'Football', 'title' => "Today's big match", 'requires' => ['sports'],
            'transition' => 'parallax', 'duration' => 8000,
        ],
        [
            'key' => 'scratch', 'kind' => 'casino', 'game' => 'scratch', 'label' => 'Scratch cards', 'requires' => ['token_launch'],
            'eyebrow' => 'Scratch & win big', 'title' => 'KSh :prize is up for grabs',
            'copy' => 'Scratch a card, reveal your prize. The next one could be the big one.',
            'cta' => ['label' => 'Scratch now', 'route' => 'games.scratch'],
            'transition' => 'slices',
        ],
        [
            'key' => 'coinflip', 'kind' => 'casino', 'game' => 'coinflip', 'label' => 'Coin Flip', 'requires' => ['token_launch'],
            'eyebrow' => 'Flip & win big', 'title' => 'Heads or tails? Make the call',
            'copy' => 'Back your call and win up to :multiplierx your stake. Let the coin decide.',
            'cta' => ['label' => 'Flip the coin', 'route' => 'casino', 'params' => ['filter' => 'coin-flip']],
            'transition' => 'iris',
        ],
        [
            'key' => 'slot-jackpots', 'kind' => 'tiers', 'game' => 'slots', 'label' => 'Slot jackpots', 'requires' => ['token_launch'],
            'eyebrow' => 'Slot jackpots', 'title' => 'Four jackpots. One spin away.',
            'copy' => 'Every spin is a shot at the Gold. Who takes it home tonight?',
            'cta' => ['label' => 'Spin the slots', 'route' => 'games.slots'],
            'transition' => 'curtain',
        ],
        [
            'key' => 'roulette', 'kind' => 'casino', 'game' => 'roulette', 'label' => 'Roulette', 'requires' => ['token_launch'],
            'eyebrow' => 'Spin & win big', 'title' => 'The roulette is calling',
            'copy' => 'Pick your numbers, watch the wheel spin and win up to :multiplierx your stake.',
            'cta' => ['label' => 'Place your bets', 'route' => 'casino', 'params' => ['filter' => 'roulette']],
            'transition' => 'fade-zoom',
        ],
        [
            'key' => 'market-1', 'kind' => 'market', 'slot' => 0, 'label' => 'Predictions', 'requires' => ['predict'],
            'eyebrow' => 'Kenya decides', 'title' => ':question',
            'copy' => 'Everyone has an opinion on this one. Call Yes or No and back yours.',
            'cta' => ['label' => 'All local markets', 'route' => 'predictions', 'params' => ['source' => 'local']],
            'transition' => 'parallax',
        ],
        [
            'key' => 'market-2', 'kind' => 'market', 'slot' => 1, 'label' => 'Predictions', 'requires' => ['predict'],
            'eyebrow' => 'Your call', 'title' => ':question',
            'copy' => 'The question the whole country is arguing about. Think you know how it ends?',
            'cta' => ['label' => 'All local markets', 'route' => 'predictions', 'params' => ['source' => 'local']],
            'transition' => 'iris',
        ],
        [
            'key' => 'slots', 'kind' => 'games', 'label' => 'Slots', 'requires' => ['token_launch'],
            'eyebrow' => 'Casino', 'title' => 'Spin a slot between rounds',
            'copy' => 'Slots and scratch cards, played right here with the same wallet.',
            'titles' => ['pharaohs_fortune', 'crown_of_embers', 'berry_blast'],
            'cta' => ['label' => 'All slots', 'route' => 'games.slots'],
            'transition' => 'slices',
        ],
        [
            'key' => 'jackpot', 'kind' => 'jackpot', 'label' => 'Sports jackpot', 'requires' => ['sports'],
            'eyebrow' => 'Sports jackpot', 'title' => 'Call every result, win :prize',
            'copy' => 'Entries close :closes.',
            'cta' => ['label' => 'Enter the jackpot', 'route' => 'sports.jackpot'],
            'art' => [['from' => 'images/og-sports.jpg', 'role' => 'bg']],
            'transition' => 'parallax',
        ],
        [
            'key' => 'invite', 'kind' => 'promo', 'label' => 'Invite & Earn', 'requires' => ['referrals'],
            'eyebrow' => 'Invite & Earn', 'title' => 'Bring a friend to the table',
            'copy' => 'Share your link and earn when the friends you invite join and play.',
            'cta' => ['label' => 'Get your link', 'route' => 'referrals', 'account' => true],
            'art' => [['from' => 'images/casino/king.png', 'role' => 'object']],
            'transition' => 'iris',
        ],
    ],
];
