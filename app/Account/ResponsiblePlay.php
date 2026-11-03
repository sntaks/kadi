<?php

namespace App\Account;

use App\Models\User;
use App\Services\KadiApiService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * A player's responsible-play state from KadiApi: whether they can play, the running break and their
 * deposit limits. Trusted for a minute; the last known state is kept for a day, so a KadiApi outage
 * never makes the site forget a running break (a break the API reported keeps blocking until it ends).
 */
class ResponsiblePlay
{
    /** @var array<int, array<string, mixed>|null> per request, so many Play buttons cost one lookup */
    private array $memo = [];

    public function __construct(private readonly KadiApiService $api) {}

    /** @return array<string, mixed>|null null when the player is not linked or the state is unknown */
    public function state(User $user): ?array
    {
        if (! $user->linked_id) {
            return null;
        }

        if (array_key_exists($user->id, $this->memo)) {
            return $this->memo[$user->id];
        }

        $state = Cache::get(self::key($user));

        // A failed lookup is never cached as the state: it backs off for 10 seconds, then asks again.
        if (! is_array($state) && ! Cache::has(self::key($user).'.failed')) {
            try {
                $state = $this->api->responsibleGambling((int) $user->linked_id);
                Cache::put(self::key($user), $state, (int) config('kadi.responsible_play.cache_seconds', 60));
                $this->remember($user, $state);
            } catch (\Throwable $e) {
                Log::warning('Responsible play: state unavailable, using the last known one', ['user' => $user->id, 'error' => class_basename($e)]);
                Cache::put(self::key($user).'.failed', true, 10);
            }
        }

        $state = is_array($state) ? $state : Cache::get(self::key($user).'.last');

        return $this->memo[$user->id] = is_array($state) && $state !== [] ? $state : null;
    }

    /**
     * The state already known, without asking KadiApi: for buttons rendered on every page, which must
     * never wait on the API. The launcher still checks live before any game opens.
     *
     * @return array<string, mixed>|null
     */
    public function known(User $user): ?array
    {
        $state = $this->memo[$user->id] ?? Cache::get(self::key($user)) ?? Cache::get(self::key($user).'.last');

        return is_array($state) && $state !== [] ? $state : null;
    }

    /** The end of the running break, or null when there is none. `$live` false reads only what is known. */
    public function breakEndsAt(User $user, bool $live = true): ?CarbonImmutable
    {
        $endsAt = ($live ? $this->state($user) : $this->known($user))['exclusion']['ends_at'] ?? null;

        if (! is_string($endsAt)) {
            return null;
        }

        $endsAt = CarbonImmutable::parse($endsAt);

        return $endsAt->isFuture() ? $endsAt : null;
    }

    public function onBreak(User $user, bool $live = true): bool
    {
        return $this->breakEndsAt($user, $live) !== null;
    }

    /** Forget the state after the player changes it, so the next read asks KadiApi. */
    public function forget(User $user): void
    {
        Cache::forget(self::key($user));
        Cache::forget(self::key($user).'.failed');
        unset($this->memo[$user->id]);
    }

    /**
     * Keep the last known state for a day, or until a running break ends if that is later: a 6-month
     * self-exclusion must keep blocking Kadi's own form even while KadiApi cannot be asked.
     *
     * @param  array<string, mixed>  $state
     */
    private function remember(User $user, array $state): void
    {
        $until = now()->addDay();
        $endsAt = $state['exclusion']['ends_at'] ?? null;

        if (is_string($endsAt) && CarbonImmutable::parse($endsAt)->greaterThan($until)) {
            $until = CarbonImmutable::parse($endsAt)->addHour();
        }

        Cache::put(self::key($user).'.last', $state, $until);
    }

    private static function key(User $user): string
    {
        return "kadi.responsible.{$user->id}";
    }
}
