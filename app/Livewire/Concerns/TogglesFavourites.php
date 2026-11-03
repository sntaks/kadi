<?php

namespace App\Livewire\Concerns;

use App\Games\Favourites;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The heart on lobby tiles. Guests are sent to log in (and back); players toggle a catalogue title,
 * at most 30 times a minute.
 */
trait TogglesFavourites
{
    public function toggleFavourite(string $code): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirectRoute('login');

            return;
        }

        if (! RateLimiter::attempt('favourites:'.$user->id, 30, fn () => true, 60)) {
            return;
        }

        app(Favourites::class)->toggle($user, $code);
    }

    /** @return list<string> */
    protected function favouriteCodes(): array
    {
        $user = auth()->user();

        return app(Favourites::class)->codes($user instanceof User ? $user : null);
    }
}
