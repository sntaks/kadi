<?php

/*
|--------------------------------------------------------------------------
| Prediction markets (docs/predictions.md)
|--------------------------------------------------------------------------
|
| The Kadi API owns the markets, the odds and the money. These are only
| this site's own settings: how often it reads, how long it keeps a copy
| and how often one player may ask. The switches are in config/platform.php.
|
*/

return [

    // Players who may place bets while platform.predictions_betting is off: users.id values, comma-separated.
    // For staff to place real bets in production before betting opens. Empty = nobody.
    'betting_testers' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('PREDICT_BETTING_TESTERS', '')),
    ))),

    'timeouts' => [
        'read' => 5,
        'quote' => 5,
        'bet' => 8,
        'connect' => 3,
    ],

    'feed' => [
        // How often the feed worker asks the API for changes.
        'interval_seconds' => 5,
        // The most `has_more` pages read in one tick.
        'max_pages' => 10,
        // No good answer for this long: pages say prices may be out of date.
        'stale_after_seconds' => 30,
        // A reference (Polymarket) price older than this cannot be bet on: the API's own guard.
        'reference_price_max_age_seconds' => 45,
        // The pages' own wire:poll, behind the live channel.
        'page_poll_seconds' => 25,
    ],

    'cache' => [
        'list_seconds' => 10,
        'market_seconds' => 5,
        'history_seconds' => 30,
        'my_bets_seconds' => 15,
        // How long the last good copy is served while the API fails.
        'stale_seconds' => 3600,
        'backoff_seconds' => 10,
    ],

    // Per minute. `estimates_site` is half the API's read budget (1,200 a minute for the whole site key, shared
    // by lists, pages, estimates and the feed), so typing in the slip can never starve the feed.
    'limits' => [
        'quotes_per_player' => 12,
        'bets_per_player' => 10,
        'estimates_per_player' => 20,
        'estimates_site' => 600,
    ],

    'sources' => ['polymarket', 'custom'],

    'categories' => ['sport', 'politics', 'entertainment'],

    'sorts' => ['closing_soon', 'trending', 'new'],

    'default_sort' => 'closing_soon',

    // Shown where a Polymarket price or chart appears and the API sent no attribution text (licence requirement).
    'attribution_fallback' => 'Reference prices from Polymarket',

];
