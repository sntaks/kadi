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
        // The feed worker runs in the background: a full list page of 100 markets can take several seconds.
        'feed' => 15,
        'quote' => 5,
        'bet' => 8,
        'connect' => 3,
    ],

    'feed' => [
        // How often the feed worker asks the API for changes.
        'interval_seconds' => 5,
        // The most `has_more` pages read in one tick.
        'max_pages' => 10,
        // A cursor further behind than this reloads the list instead of catching up through /updates, whose answer
        // would be too big to arrive within the read timeout.
        'max_catch_up_seconds' => 300,
        // No good answer for this long: pages say prices may be out of date.
        'stale_after_seconds' => 30,
        // A reference (Polymarket) price older than this cannot be bet on: the API's own guard.
        'reference_price_max_age_seconds' => 45,
        // The pages' own wire:poll, behind the live channel.
        'page_poll_seconds' => 25,
        // The most list pages (100 markets each) read when the feed starts without a cursor.
        'bootstrap_max_pages' => 20,
        // Markets per broadcast message.
        'broadcast_batch' => 100,
        // A tick that takes longer than this frees the lock for another worker.
        'lock_seconds' => 180,
        // A market the API stops announcing leaves the feed after this long (pages then read it from the API).
        'record_ttl_seconds' => 172800,
    ],

    // platform:health reports the feed when it has not had a good answer for this long while predictions are on.
    'health' => [
        'feed_stale_minutes' => 2,
    ],

    // Markets per lobby page (the API allows up to 100).
    'per_page' => 24,

    'cache' => [
        // A list page. Like a market, its odds are replaced by the feed's whenever the feed's copy is newer.
        'list_seconds' => 30,
        // One market's text and figures. Its live part (status, betting, odds) is taken from the feed whenever the
        // feed's copy is newer, so the text can be kept longer than the odds would allow on their own.
        'market_seconds' => 20,
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
