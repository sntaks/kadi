<?php

/*
|--------------------------------------------------------------------------
| Landing hero slider
|--------------------------------------------------------------------------
|
| The slides of the landing's hero carousel (768px and wider), in order. Only
| App\Games\HeroSlides reads this: it drops any slide whose `requires` gates are
| off, whose CTA route does not exist, whose games the catalogue does not offer
| or (jackpot) while no jackpot takes entries. The `kadi` slide (the Kadi table)
| is always first; the `match` slide (today's big match) follows while sports is
| on and there is a big match. On phones only those two show, stacked as before.
|
| Copy only: never put a figure here that the site cannot back. `:amount` is the
| signup bonus (kadi.promotions.signup_bonus_amount), `:prize` the open jackpot's
| real prize total and `:closes` its closing time.
|
| `art[].from` is an image under public/; `php artisan hero:artwork` builds the
| sizes the page uses into public/images/hero/. Commit them.
|
| `requires`: promotions, referrals, sports, token_launch. `cta.account`: the CTA is
| an account-only page (guests get the sign-in sheet, like every such link).
| `transition`: curtain, iris, slices, parallax, fade-zoom.
|
*/

return [
    'max_slides' => 6,

    'durations' => ['default' => 7000], // ms; a slide's own `duration` wins

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
            'key' => 'welcome', 'kind' => 'promo', 'label' => 'Welcome bonus', 'requires' => ['promotions'],
            'eyebrow' => 'New here?', 'title' => 'KES :amount welcome bonus',
            'copy' => 'Verify your e-mail and phone number and the bonus lands in your wallet.',
            'cta' => ['label' => 'See promotions', 'route' => 'promotions'],
            'art' => [['from' => 'images/casino/crown.png', 'role' => 'object']],
            'transition' => 'iris',
        ],
        [
            'key' => 'slots', 'kind' => 'games', 'label' => 'Slots', 'requires' => ['token_launch'],
            'eyebrow' => 'Casino', 'title' => 'Spin a slot between rounds',
            'copy' => 'Slots and scratch cards, played right here with the same wallet.',
            'titles' => ['dragon_fire_fortune', 'crown_of_embers', 'berry_blast'],
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
        [
            'key' => 'install', 'kind' => 'install', 'label' => 'Get the app',
            'eyebrow' => 'On your phone', 'title' => 'Kadi on your home screen',
            'copy' => 'One tap to the tables, and announcements as notifications.',
            'art' => [['from' => 'images/casino/kadi.png', 'role' => 'object']],
            'transition' => 'fade-zoom',
        ],
    ],
];
