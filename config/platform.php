<?php

/*
|--------------------------------------------------------------------------
| Multi-game platform switches
|--------------------------------------------------------------------------
|
| Each switch turns on one part of the move from single-game Kadi to the
| Kadi, Casino and Sports site (plans/kadi-multi-game-overhaul-plan.md).
| All default to off, so a deploy changes nothing until a switch is set.
|
*/

return [

    // The Night table look: new app shell, landing, casino and game pages.
    'ui_v2' => (bool) env('PLATFORM_UI_V2', false),

    // Launch every game with a one-time launch token instead of the old
    // Kadi play form, and play it inside the site.
    'token_launch' => (bool) env('PLATFORM_TOKEN_LAUNCH', false),

    // Shareable /play/{game}/{title} links that open a game directly.
    'deep_link' => (bool) env('PLATFORM_DEEP_LINK', false),

    // Sports pages: fixtures, odds and the bet slip (browse only).
    'sports' => (bool) env('PLATFORM_SPORTS', false),

    // Placing sports bets through the Kadi API wallet. Needs `sports` on.
    'sports_betting' => (bool) env('PLATFORM_SPORTS_BETTING', false),

];
