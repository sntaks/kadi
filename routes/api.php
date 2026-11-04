<?php

use App\Http\Controllers\Api\Backoffice\SportsController;
use App\Http\Controllers\Api\PushApiController;
use App\Http\Controllers\Api\PushBroadcastController;
use App\Http\Controllers\Api\WalletWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Server-to-server API
|--------------------------------------------------------------------------
|
| Registered under the `api` middleware group (prefix /api): no session, no cookies, no CSRF.
| Authentication is a bearer key checked by the `push.api` middleware, which fails closed when
| no key is configured. See docs/push-api.md.
|
*/

Route::prefix('v1')->middleware(['push.api', 'throttle:push-api'])->group(function () {
    Route::post('push-notifications', PushApiController::class)->name('api.v1.push-notifications.store');
});

// KadiApi -> this site: wallet balance changes, signature-verified. See docs/wallet-webhook.md.
Route::prefix('v1')->middleware(['kadi.webhook', 'throttle:60,1'])->group(function () {
    Route::post('wallet-webhooks', WalletWebhookController::class)->name('api.v1.wallet-webhooks.store');
});

// System-wide announcements to every registered device. A separate key list (`push.api:broadcast`),
// so the per-player key cannot notify everyone.
Route::prefix('v1/push-broadcasts')->middleware(['push.api:broadcast', 'throttle:push-api'])->name('api.v1.push-broadcasts.')->group(function () {
    Route::post('/', [PushBroadcastController::class, 'store'])->name('store');
    Route::get('{broadcast}', [PushBroadcastController::class, 'show'])->name('show');
    Route::delete('{broadcast}', [PushBroadcastController::class, 'destroy'])->name('destroy');
});

// GMS (staff back office) -> this site: sports operations, HMAC-signed and idempotent by request id. GMS reads lists
// straight from this database; only actions and figures that need this app's rules are here. See docs/backoffice-api.md.
Route::prefix('v1/backoffice/sports')->middleware(['backoffice', 'throttle:backoffice'])->name('api.v1.backoffice.sports.')
    ->controller(SportsController::class)->group(function () {
        Route::get('exposure', 'exposure')->name('exposure');
        Route::get('reconciliation', 'reconciliation')->name('reconciliation');
        Route::get('odds-api/credits', 'oddsApiCredits')->name('odds-api.credits');
        Route::get('jackpots/{jackpot}/tiers', 'jackpotTiers')->name('jackpots.tiers');

        Route::post('markets/{market}/suspend', 'suspendMarket')->name('markets.suspend');
        Route::post('markets/{market}/reopen', 'reopenMarket')->name('markets.reopen');
        Route::post('markets/{market}/prices', 'overridePrices')->name('markets.prices');
        Route::post('fixtures/{fixture}/result', 'recordResult')->name('fixtures.result');
        Route::patch('competitions/{competition:id}', 'updateCompetition')->name('competitions.update');

        Route::post('bet-legs/{leg}/void', 'voidLeg')->name('bet-legs.void');
        Route::post('bets/{bet}/cancel', 'cancelBet')->name('bets.cancel');
        Route::post('wager-ops/{op}/retry', 'retryOp')->name('wager-ops.retry');

        Route::post('jackpots', 'draftJackpot')->name('jackpots.store');
        Route::put('jackpots/{jackpot}', 'draftJackpot')->name('jackpots.update');
        Route::post('jackpots/{jackpot}/publish', 'publishJackpot')->name('jackpots.publish');
        Route::post('jackpots/{jackpot}/cancel', 'cancelJackpot')->name('jackpots.cancel');
    });
