<?php

namespace App\Auth;

use App\Events\SignInRefused;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The web guard. Every way of signing in (password, two-factor challenge, Google, passkey, remember-me cookie) ends in
 * login() or the remember-me recall, so the one-device rule lives here rather than in each controller.
 */
class SingleSessionGuard extends SessionGuard
{
    public function login(Authenticatable $user, $remember = false)
    {
        $singleSession = app(SingleSession::class);

        if ($singleSession->signedInElsewhere($user, $this->session->getId())) {
            $this->session->put(SingleSession::PENDING_KEY, $user->getAuthIdentifier());

            if ($user instanceof User) {
                SignInRefused::dispatch($user);
            }

            throw SignedInElsewhere::make();
        }

        // A new sign-in retires every other device's remember-me cookie, so none of them can quietly start a second
        // session later. Before parent::login(), which puts the fresh token in this device's cookie.
        if ($singleSession->enforced() && $user->getRememberToken() !== null) {
            $this->cycleRememberToken($user);
        }

        $this->session->forget(SingleSession::PENDING_KEY);

        parent::login($user, $remember);
    }

    /** A remember-me cookie never signs a player in while another device holds their session. */
    protected function userFromRecaller($recaller)
    {
        $user = parent::userFromRecaller($recaller);

        if ($user !== null && app(SingleSession::class)->signedInElsewhere($user, $this->session->getId())) {
            $this->viaRemember = false;

            return null;
        }

        return $user;
    }
}
