<?php

/*
|--------------------------------------------------------------------------
| Multi-game platform switches
|--------------------------------------------------------------------------
|
| The Kadi, Casino and Sports site is the only site (plan Phase 12): the
| old look is gone. What is left are switches for parts of it: on by
| default, except taking real-money sports and prediction bets, which an
| environment must turn on deliberately (PLATFORM_SPORTS_BETTING=true,
| PLATFORM_PREDICTIONS_BETTING=true).
|
*/

return [

    // Launch every game with a one-time launch token and play it inside the
    // site. Off: the casino closes and Kadi uses its ggid form again.
    'token_launch' => (bool) env('PLATFORM_TOKEN_LAUNCH', true),

    // Crawlable game pages (/slots, /scratch-cards, /games/{slug}) that link straight into
    // /play/{game}/{title}. The play links themselves follow token_launch.
    'deep_link' => (bool) env('PLATFORM_DEEP_LINK', true),

    // Sports pages: fixtures, odds and the bet slip (browse only).
    'sports' => (bool) env('PLATFORM_SPORTS', true),

    // Placing sports bets through the Kadi API wallet (real money). Needs `sports` on. Off unless set, so a deploy
    // never starts taking bets on an environment nobody decided to open.
    'sports_betting' => (bool) env('PLATFORM_SPORTS_BETTING', false),

    // Prediction market pages (/predictions): markets, prices and the slip (browse only).
    'predictions' => (bool) env('PLATFORM_PREDICTIONS', true),

    // Placing prediction bets through the Kadi API wallet (real money). Needs `predictions` on. Off unless set;
    // PREDICT_BETTING_TESTERS (config/predict.php) can bet while it is off.
    'predictions_betting' => (bool) env('PLATFORM_PREDICTIONS_BETTING', false),

];
