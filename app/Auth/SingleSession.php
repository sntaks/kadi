<?php

namespace App\Auth;

use App\Listeners\EndGameSessionsOnLogout;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One signed-in device per player. A player with a live session elsewhere is refused a new sign-in until that device
 * signs out, its session ends, or they release it with the e-mailed link (ReleaseDeviceController).
 *
 * "Live" means the session row was active within the shorter of the session lifetime and the idle timeout, because
 * LogoutInactiveUsers signs an idle session out at its next request anyway. Only the database session driver lets us
 * see other devices; with any other driver (tests, local) the rule is off.
 */
class SingleSession
{
    /**
     * The session key holding the player who was just refused (`id`, `remember`, `at`), for the takeover button and the
     * release-link request. Set only by the guard's login(), i.e. after the password, two-factor, Google or passkey
     * check has passed.
     */
    public const PENDING_KEY = 'single_session.refused_user';

    /** How long after the refusal the player may still choose "sign the other device out and play here". */
    public const TAKEOVER_SECONDS = 300;

    public function enforced(): bool
    {
        return (bool) config('auth.single_session', true) && config('session.driver') === 'database';
    }

    public function signedInElsewhere(Authenticatable $user, ?string $currentSessionId): bool
    {
        if (! $this->enforced()) {
            return false;
        }

        return $this->sessions($user)
            ->where('last_activity', '>=', now()->subMinutes($this->liveMinutes())->getTimestamp())
            ->when($currentSessionId !== null, fn (Builder $query) => $query->where('id', '!=', $currentSessionId))
            ->exists();
    }

    /** Signs the player out everywhere except (optionally) the given session. Returns how many sessions ended. */
    public function endOtherSessions(Authenticatable $user, ?string $keepSessionId = null): int
    {
        return $this->sessions($user)
            ->when($keepSessionId !== null, fn (Builder $query) => $query->where('id', '!=', $keepSessionId))
            ->delete();
    }

    public function park(Session $session, Authenticatable $user, bool $remember): void
    {
        $session->put(self::PENDING_KEY, ['id' => $user->getAuthIdentifier(), 'remember' => $remember, 'at' => now()->getTimestamp()]);
    }

    /**
     * Takes the refused player out of the session (single use). Null when there is none, or (with $fresh) when the
     * refusal is older than TAKEOVER_SECONDS.
     *
     * @return array{user: User, remember: bool}|null
     */
    public function pullPending(Session $session, bool $fresh = false): ?array
    {
        $pending = $session->pull(self::PENDING_KEY);

        if (! is_array($pending) || ! isset($pending['id'], $pending['at'])) {
            return null;
        }

        if ($fresh && now()->getTimestamp() - (int) $pending['at'] > self::TAKEOVER_SECONDS) {
            return null;
        }

        $user = User::find($pending['id']);

        return $user === null ? null : ['user' => $user, 'remember' => (bool) ($pending['remember'] ?? false)];
    }

    /**
     * Signs every other device out: their sessions, their remember-me cookies, and (as on any logout) their games
     * and sports token.
     */
    public function releaseOtherDevices(User $user): void
    {
        $this->endOtherSessions($user);
        $user->setRememberToken(Str::random(60));
        $user->save();

        app(EndGameSessionsOnLogout::class)->handle(new Logout('web', $user));
    }

    public function liveMinutes(): int
    {
        $lifetime = (int) config('session.lifetime', 120);
        $idle = (int) config('auth.idle_timeout', 0);

        return $idle > 0 ? min($lifetime, $idle) : $lifetime;
    }

    private function sessions(Authenticatable $user): Builder
    {
        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier());
    }
}
