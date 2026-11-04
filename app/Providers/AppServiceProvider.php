<?php

namespace App\Providers;

use App\Account\ResponsiblePlay;
use App\Events\PasswordChanged;
use App\Games\GameCatalogue;
use App\Games\LaunchFailed;
use App\Http\Controllers\GameLaunchController;
use App\Listeners\RecordSecurityAudit;
use App\Listeners\SendSecurityNotification;
use App\Models\User;
use App\Services\BugsApiService;
use App\Services\KadiApiService;
use App\Sportsbook\Betting\NullSportsWallet;
use App\Sportsbook\Betting\SportsWallet;
use App\Sportsbook\OddsApi\CreditBudget;
use App\Sportsbook\OddsApi\LiveOddsApi;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\OddsApi\ReplayOddsApi;
use App\Sportsbook\Pricing\ConsensusPricer;
use App\Sportsbook\Pricing\Derived\DerivedMarketPricer;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One catalogue per request (and per queued job): a layout and a component share one pass.
        $this->app->scoped(GameCatalogue::class);

        // The merged sportsbook (plan Phase 7). Replay serves recordings and never spends Odds API credits.
        $this->app->bind(CreditBudget::class, fn (): CreditBudget => new CreditBudget(
            monthlyCap: (int) config('sportsbook.odds_api.monthly_credit_cap'),
            reserve: (int) config('sportsbook.odds_api.credit_reserve'),
        ));
        $this->app->bind(OddsApi::class, fn (Application $app): OddsApi => config('sportsbook.odds_api.driver') === 'live'
            ? LiveOddsApi::fromConfig($app->make(CreditBudget::class))
            : new ReplayOddsApi((string) config('sportsbook.odds_api.recordings_path')));
        $this->app->bind(ConsensusPricer::class, fn (): ConsensusPricer => new ConsensusPricer(
            margin: (float) config('sportsbook.odds.margin'),
            minimumBookmakers: (int) config('sportsbook.odds.minimum_bookmakers'),
        ));
        $this->app->bind(DerivedMarketPricer::class, fn (): DerivedMarketPricer => new DerivedMarketPricer(
            margin: (float) config('sportsbook.odds.margin'),
            modelMargin: (float) config('sportsbook.odds.derived_market_margin'),
            firstHalfShare: (float) config('sportsbook.odds.first_half_goal_share'),
            maximumModelPrice: (float) config('sportsbook.odds.derived_maximum_price'),
        ));
        // No money moves for sports until bets are paid through the Kadi API (Phase 8).
        $this->app->bind(SportsWallet::class, NullSportsWallet::class);
        $this->app->scoped(ResponsiblePlay::class);

        $this->app->singleton(KadiApiService::class, function ($app) {
            return new KadiApiService;
        });
        $this->app->singleton(BugsApiService::class, function ($app) {
            return new BugsApiService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Listeners in app/Listeners (HandleLogin, HandleEmailVerified, FlagConsentRequired,
        // LogPushFailure) are NOT registered here on purpose: Laravel auto-discovers any class
        // whose handle() is type-hinted with an event, so listing one with Event::listen() as
        // well makes it run twice per event. tests/Feature/ListenerRegistrationTest.php guards this.
        // Only listeners that auto-discovery cannot see (like the multi-event security ones
        // below) are registered by hand.

        $this->configureRateLimiting();
        $this->configureSecurityEventListeners();
        $this->configureLogViewerAccess();
    }

    /**
     * The production log viewer (opcodesio/log-viewer, at /log-viewer) shows raw application logs, which
     * can hold personal data, so only admins may open it. The gate is always defined: with no gate the
     * package would let everyone in outside production. Guests get a 403.
     */
    protected function configureLogViewerAccess(): void
    {
        Gate::define('viewLogViewer', fn (?User $user) => $user?->isAdmin() ?? false);
    }

    /**
     * Push subscription endpoints: 20 requests a minute per user. Registering a
     * device is a rare, human-paced action, so this is generous but caps abuse.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('push', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        // Server-to-server push API: per API key (falls back to IP), so one key cannot starve others.
        RateLimiter::for('push-api', fn (Request $request) => Limit::perMinute((int) config('kadi.push_api.requests_per_minute'))
            ->by($request->attributes->get('push_api_key_id') ?: $request->ip()));

        // Each launch spends one of the site key's 30 launch tokens a minute (Kadi API), so one
        // player can't use them up. Over the limit the frame shows a plain "wait a minute".
        RateLimiter::for('game-launch', fn (Request $request) => Limit::perMinute((int) config('games.launch.per_minute', 6))
            ->by('game-launch:'.($request->user()?->getAuthIdentifier() ?: $request->ip()))
            ->response(fn () => GameLaunchController::page([
                'error' => LaunchFailed::MESSAGES['rate_limited'],
                'game' => null,
            ], 429)));

        // Test sends hit a real push service, so keep them well below the subscription limit.
        RateLimiter::for('push-test', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }

    /**
     * Audit + notify on account security credential changes.
     */
    protected function configureSecurityEventListeners(): void
    {
        $events = [
            TwoFactorAuthenticationEnabled::class,
            TwoFactorAuthenticationConfirmed::class,
            TwoFactorAuthenticationDisabled::class,
            RecoveryCodesGenerated::class,
            RecoveryCodeReplaced::class,
            PasskeyRegistered::class,
            PasskeyDeleted::class,
            PasswordChanged::class,
        ];

        foreach ($events as $event) {
            Event::listen($event, RecordSecurityAudit::class);
            Event::listen($event, SendSecurityNotification::class);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Model::automaticallyEagerLoadRelationships();
        Model::unguard();

        if (app()->environment('production')) {
            URL::forceHttps();
        }

        // Fail fast when the shared KadiApi encryption key is missing or
        // malformed outside local/testing. Silent fallbacks here would put
        // money-endpoint customer-ID encryption at risk (audit finding C-2).
        //        if (! app()->environment('local', 'testing')) {
        //            openssl_shared_key();
        //        }

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
