<?php

namespace App\Predict;

use App\Models\User;
use App\Predict\Data\PredictBet;
use Illuminate\Support\Facades\Cache;

/**
 * A player's prediction bets (GET customers/{id}/predict/bets), one tab at a time: open, or settled (won, lost and
 * void together, the API's own `settled` filter). Cached 15 seconds per player, tab and page; a placement or an
 * unknown outcome bumps the player's version (MyBetsCache), so the next read asks the API. A failed read serves the
 * last good copy. The bets' `reference` is never kept (PredictBet has no field for it).
 */
class MyBets
{
    public const TABS = ['open', 'settled'];

    public const PER_PAGE = 20;

    public function __construct(private PredictApi $api) {}

    /**
     * @return array{bets: list<PredictBet>, page: int, last_page: int, total: int, available: bool}
     */
    public function page(User $user, string $tab, int $page = 1): array
    {
        $tab = in_array($tab, self::TABS, true) ? $tab : 'open';
        $page = max(1, $page);
        $key = sprintf('predict.my-bets.%d.%d.%s.%d', $user->getKey(), MyBetsCache::version($user), $tab, $page);
        // The last good copy outlives version bumps: it is what an outage shows.
        $staleKey = sprintf('predict.my-bets.%d.%s.%d.stale', $user->getKey(), $tab, $page);
        $data = Cache::get($key);

        if (! is_array($data)) {
            $response = $this->api->bets($user, $tab, $page, self::PER_PAGE);

            if ($response->ok() && is_array($response->data['items'] ?? null)) {
                $data = $response->data;
                Cache::put($key, $data, (int) config('predict.cache.my_bets_seconds', 15));
                Cache::put($staleKey, $data, 3600);
            } else {
                $data = Cache::get($staleKey);
            }
        }

        if (! is_array($data)) {
            return ['bets' => [], 'page' => $page, 'last_page' => 1, 'total' => 0, 'available' => false];
        }

        $bets = array_values(array_filter(array_map(
            fn ($bet) => is_array($bet) ? PredictBet::fromArray($bet) : null,
            (array) $data['items'],
        )));
        $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];

        return [
            'bets' => $bets,
            'page' => (int) ($pagination['page'] ?? $page),
            'last_page' => max(1, (int) ($pagination['last_page'] ?? 1)),
            'total' => (int) ($pagination['total'] ?? count($bets)),
            'available' => true,
        ];
    }
}
