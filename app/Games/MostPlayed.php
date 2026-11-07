<?php

namespace App\Games;

use App\Models\GameLaunch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * "Most played": titles ranked by how many different players launched them in the last 30 days,
 * from our own launch log (game_launches). A title needs a few players before it is ranked, and
 * with too little data the row is simply not shown: it is never padded with curated picks.
 */
class MostPlayed
{
    public const DAYS = 30;

    public const MIN_PLAYERS = 3;

    public const MIN_TITLES = 4;

    public function __construct(private readonly GameCatalogue $catalogue) {}

    /** @return Collection<int, GameTitle> at most $limit, best first; empty when there is not enough data */
    public function titles(int $limit = 12): Collection
    {
        $codes = Cache::remember('games.most_played', 600, fn () => GameLaunch::query()
            ->where('status', 'launched')
            ->whereNotNull('product')
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->groupBy('product')
            ->havingRaw('COUNT(DISTINCT user_id) >= ?', [self::MIN_PLAYERS])
            ->orderByRaw('COUNT(DISTINCT user_id) DESC')
            ->orderBy('product')
            ->limit(50)
            ->pluck('product')
            ->all());

        $titles = $this->catalogue->titles()->keyBy('key');
        $ranked = collect($codes)->map(fn (string $code) => $titles->get($code))->filter()->take($limit)->values();

        return $ranked->count() >= self::MIN_TITLES ? $ranked : collect();
    }
}
