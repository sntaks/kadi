<?php

namespace App\Observers;

use App\Activity\PlayerActivity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * A player's own profile changes in the player activity log: which fields changed, never their values. Changes the
 * system makes (sign-in bookkeeping, syncs, staff) are not the player's activity and are left out.
 */
class UserActivityObserver
{
    private const FIELDS = ['name', 'email', 'phone', 'avatar', 'password'];

    public function updated(User $user): void
    {
        if (Auth::id() !== $user->id) {
            return;
        }

        $fields = array_values(array_intersect(self::FIELDS, array_keys($user->getChanges())));

        if ($fields !== []) {
            app(PlayerActivity::class)->record('profile_updated', $user, ['fields' => $fields]);
        }
    }
}
