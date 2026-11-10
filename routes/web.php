<?php

use App\Games\GameCatalogue;
use App\Http\Controllers\Auth\ConsentController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\GoogleLinkController;
use App\Http\Controllers\Auth\NameUpdateController;
use App\Http\Controllers\Dev\MailPreviewController;
use App\Http\Controllers\Dev\UiGalleryController;
use App\Http\Controllers\GameLaunchController;
use App\Http\Controllers\GameSearchController;
use App\Http\Controllers\ProfilePictureController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PushTestController;
use App\Http\Middleware\RequirePlatformFlag;
use App\Livewire\Account\Activity;
use App\Livewire\Account\ResponsiblePlay as ResponsiblePlayPage;
use App\Livewire\Faq;
use App\Livewire\GameGuide;
use App\Livewire\Games\Play;
use App\Livewire\Legal\Privacy;
use App\Livewire\Legal\Terms;
use App\Livewire\Lobby\Casino;
use App\Livewire\Lobby\GameIndex;
use App\Livewire\Lobby\GameShow;
use App\Livewire\Profile\Show;
use App\Livewire\Promotions;
use App\Livewire\Referrals\Index as ReferralsIndex;
use App\Livewire\Rules;
use App\Livewire\Sports\CompetitionPage as SportsCompetition;
use App\Livewire\Sports\Home as SportsHome;
use App\Livewire\Sports\JackpotPage as SportsJackpot;
use App\Livewire\Sports\Live as SportsLive;
use App\Livewire\Sports\MatchPage as SportsMatch;
use App\Livewire\Sports\Search as SportsSearch;
use App\Livewire\Wallet\Index;
use App\Livewire\Welcome;
use App\Sportsbook\Models\Competition;
use Illuminate\Http\Request;
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

// Every offer in one place (plan Phase 11), for guests and players.
Route::get('/promotions', Promotions::class)->name('promotions');

// The casino (plan Phase 5): browse for everyone once games open inside the site.
Route::middleware(RequirePlatformFlag::class.':token_launch')->group(function () {
    Route::get('/casino/{filter?}', Casino::class)->where('filter', '[a-z-]+')->name('casino');
});

// Crawlable game pages (PLATFORM_DEEP_LINK), built on the new look. Their Play buttons follow token_launch.
Route::middleware(RequirePlatformFlag::class.':deep_link')->group(function () {
    Route::get('/slots', GameIndex::class)->defaults('game', 'slots')->name('games.slots');
    Route::get('/scratch-cards', GameIndex::class)->defaults('game', 'scratch')->name('games.scratch');
    // Never /games/history or /games/search, which are their own pages.
    Route::get('/games/{slug}', GameShow::class)->where('slug', '(?!(?:'.implode('|', GameCatalogue::RESERVED_SLUGS).')$)[a-z0-9-]+')->name('games.show');
});

// Sports (plan Phase 9): football lists, matches, the sports jackpot and in-play list, in the new shell. Betting
// itself also needs PLATFORM_SPORTS_BETTING (App\Sportsbook\Wagers\WagerPlacement).
Route::middleware(RequirePlatformFlag::class.':sports')->prefix('sports')->group(function () {
    Route::get('/', SportsHome::class)->name('sports');
    Route::get('/jackpot', SportsJackpot::class)->name('sports.jackpot');
    Route::get('/live', SportsLive::class)->name('sports.live');
    Route::get('/search', SportsSearch::class)->name('sports.search');
    Route::get('/match/{fixture}', SportsMatch::class)->whereNumber('fixture')->name('sports.match');
    // One page per sport (config sportsbook.sports). Football keeps /sports, so its own slug redirects there.
    Route::permanentRedirect('/football', '/sports');
    Route::get('/{sport}', SportsHome::class)
        ->whereIn('sport', array_values(array_diff(array_keys((array) config('sportsbook.sports')), ['football'])))
        ->name('sports.sport');
    Route::get('/{competition:slug}', SportsCompetition::class)
        ->where('competition', '(?!(?:'.implode('|', Competition::RESERVED_SLUGS).')$)[a-z0-9-]+')
        ->name('sports.competition');
});

// Top bar search index (fetched on first focus). Only once games open inside the site.
// Games while they open inside the site, and football while the sports pages are on: the controller checks both.
Route::get('/games/search', GameSearchController::class)->middleware('throttle:60,1')->name('games.search');

Route::middleware(['auth'])->group(function () {
    Route::get('/consent', [ConsentController::class, 'show'])->name('consent.show');
    Route::post('/consent', [ConsentController::class, 'store'])->name('consent.store');
    Route::get('/name', [NameUpdateController::class, 'show'])->name('name.edit');
    Route::post('/name', [NameUpdateController::class, 'store'])->name('name.update');
    Route::get('/auth/google/link', [GoogleLinkController::class, 'redirect'])->name('auth.google.link');
    Route::get('/auth/google/link/callback', [GoogleLinkController::class, 'callback'])->name('auth.google.link.callback');
});

// Pages the old site had (plan Phase 12). Their names and addresses keep working for bookmarks, e-mails and push
// links, carrying the query string on: the dashboard is the landing, the profile is /account, Kadi's game history
// is the Kadi tab of /activity.
$moved = fn (string $route) => fn (Request $request) => redirect()->to(route($route).($request->getQueryString() ? '?'.$request->getQueryString() : ''));
Route::get('/dashboard', $moved('home'))->name('dashboard');
Route::get('/profile', $moved('account'))->name('profile');
Route::get('/games/history', $moved('activity'))->name('games.history');

Route::middleware(['auth', 'verified'])->group(function () {
    // The account area (plan Phase 6): one header and sub-tabs for the account page, wallet, activity,
    // Invite & Earn and responsible play.
    Route::get('/account', Show::class)->name('account');
    Route::get('/activity', Activity::class)->name('activity');
    Route::get('/responsible-play', ResponsiblePlayPage::class)->name('responsible-play');
    Route::post('/profile/picture', [ProfilePictureController::class, 'upload'])->name('profile.picture');
    Route::get('/wallet', Index::class)->name('wallet');
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
