<?php

use App\Http\Controllers\Auth\ConsentController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\GoogleLinkController;
use App\Http\Controllers\Auth\NameUpdateController;
use App\Http\Controllers\Dev\MailPreviewController;
use App\Http\Controllers\Dev\UiGalleryController;
use App\Http\Controllers\GameLaunchController;
use App\Http\Controllers\ProfilePictureController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PushTestController;
use App\Http\Middleware\RequirePlatformFlag;
use App\Livewire\Dashboard;
use App\Livewire\Faq;
use App\Livewire\GameGuide;
use App\Livewire\Games\History as GameHistory;
use App\Livewire\Games\Play;
use App\Livewire\Legal\Privacy;
use App\Livewire\Legal\Terms;
use App\Livewire\Profile\Show;
use App\Livewire\Referrals\Index as ReferralsIndex;
use App\Livewire\Rules;
use App\Livewire\Wallet\Index;
use App\Livewire\Welcome;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::get('/auth/google/complete', [GoogleAuthController::class, 'complete'])->name('auth.google.complete');
Route::post('/auth/google/complete', [GoogleAuthController::class, 'storeComplete'])->name('auth.google.complete.store');
Route::get('/auth/google/cancel', [GoogleAuthController::class, 'cancel'])->name('auth.google.complete.cancel');

Route::get('/', Welcome::class)->name('home');
Route::get('/how-to', Rules::class)->name('rules');
Route::get('/games-guide', GameGuide::class)->name('game-guide');
Route::get('/faq', Faq::class)->name('faq');

Route::middleware(['auth'])->group(function () {
    Route::get('/consent', [ConsentController::class, 'show'])->name('consent.show');
    Route::post('/consent', [ConsentController::class, 'store'])->name('consent.store');
    Route::get('/name', [NameUpdateController::class, 'show'])->name('name.edit');
    Route::post('/name', [NameUpdateController::class, 'store'])->name('name.update');
    Route::get('/auth/google/link', [GoogleLinkController::class, 'redirect'])->name('auth.google.link');
    Route::get('/auth/google/link/callback', [GoogleLinkController::class, 'callback'])->name('auth.google.link.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/profile', Show::class)->name('profile');
    Route::post('/profile/picture', [ProfilePictureController::class, 'upload'])->name('profile.picture');
    Route::get('/wallet', Index::class)->name('wallet');
    Route::get('/games/history', GameHistory::class)->name('games.history');
    Route::get('/referrals', ReferralsIndex::class)->name('referrals');

    // Games inside the site (plan §4a). The page never holds a launch token: its form posts
    // to play.launch inside the frame, which redirects once to play.open, which posts on to the game.
    // Every page that can start a game handles errors itself (frame message), so none are blank.
    Route::middleware(RequirePlatformFlag::class.':token_launch')->group(function () {
        Route::post('/play/{game}', [GameLaunchController::class, 'store'])
            ->middleware('throttle:game-launch')->name('play.launch');
        Route::get('/play/{game}/open/{handoff}', [GameLaunchController::class, 'open'])->name('play.open');
        Route::get('/play/{game}/{title?}', Play::class)->where('title', '[a-z0-9-]+')->name('play');
    });

    // Web push device registration. Throttled per user; CSRF applies like any other web POST/DELETE.
    Route::middleware('throttle:push')->prefix('push/subscriptions')->name('push.subscriptions.')->group(function () {
        Route::post('/', [PushSubscriptionController::class, 'store'])->name('store');
        Route::delete('/', [PushSubscriptionController::class, 'destroy'])->name('destroy');
    });

    // Sends a real test push to the caller's own devices. Local/staging or admin roles only (checked in the controller).
    Route::post('push/test', PushTestController::class)->middleware('throttle:push-test')->name('push.test');
});

Route::get('/terms', Terms::class)->name('legal.terms');
Route::get('/privacy', Privacy::class)->name('legal.privacy');

Route::get('/email/preview/{type}', MailPreviewController::class)->name('mail.preview');
Route::get('/_ui', UiGalleryController::class)->name('ui.gallery');

Route::get('/sitemap.xml', function () {
    $path = public_path('sitemap.xml');
    abort_unless(file_exists($path), 404);

    return response()->file($path, ['Content-Type' => 'application/xml']);
});

require __DIR__.'/settings.php';
