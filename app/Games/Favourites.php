<?php

namespace App\Games;

use App\Models\GameFavourite;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A player's hearted titles. Only titles the catalogue offers can be added, and only those are
 * listed, so a title the API drops disappears from favourites without a clean-up job.
 */
class Favourites
{
    /** Most a player can keep (`games.favourites_limit`); the heart refuses more rather than growing without end. */
    public static function limit(): int
    {
        return (int) config('games.favourites_limit', 100);
    }

    public function __construct(private readonly GameCatalogue $catalogue) {}

    /** @return list<string> title keys (GameTitle::$key), newest first */
    public function codes(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return GameFavourite::query()->where('user_id', $user->id)->latest('id')->pluck('product')->all();
    }

    /**
     * @param  list<string>|null  $codes  the player's codes when already loaded, to save a query
     * @return Collection<int, GameTitle> in the order they were hearted, newest first
     */
    public function titles(?User $user, ?array $codes = null): Collection
    {
        $titles = $this->catalogue->titles()->keyBy('key');

        return collect($codes ?? $this->codes($user))->map(fn (string $code) => $titles->get($code))->filter()->values();
    }

    /**
     * Adds or removes a title. Removing always works, even for a title the catalogue has since dropped;
     * adding needs a catalogue title and room under the limit (which counts only titles still offered).
     * Returns the new state, or null when nothing changed.
     */
    public function toggle(User $user, string $code): ?bool
    {
        // Stored by key, so a prefix the API adds or drops never loses a favourite. Takes the API code too.
        $title = $this->catalogue->title($code);
        $key = $title->key ?? $code;

        if (GameFavourite::query()->where('user_id', $user->id)->where('product', $key)->delete() > 0) {
            return false;
        }

        if ($title === null || $this->titles($user)->count() >= self::limit()) {
            return null;
        }

        // A double tap sends two requests at once: createOrFirst rides out the unique index instead of failing.
        GameFavourite::query()->createOrFirst(['user_id' => $user->id, 'product' => $key]);

        return true;
    }
}
