<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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
    /** The session key holding the player who was just refused, for the release-link request. */
    public const PENDING_KEY = 'single_session.refused_user';

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
