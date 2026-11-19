<?php

namespace App\Auth;

use App\Events\SignInRefused;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The web guard. Every way of signing in (password, two-factor challenge, Google, passkey, remember-me cookie) ends in
 * login() or the remember-me recall, so the one-device rule lives here rather than in each controller. The same device
 * signing in again (App\Auth\DeviceId) is never refused: its old session is replaced.
 */
class SingleSessionGuard extends SessionGuard
{
    public function login(Authenticatable $user, $remember = false)
    {
        $singleSession = app(SingleSession::class);
        $deviceId = DeviceId::from($this->getRequest());

        if ($singleSession->signedInElsewhere($user, $this->session->getId(), $deviceId)) {
            $singleSession->park($this->session, $user, (bool) $remember);

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

        // The same device coming back (its session cookie was lost): its old session simply ends, no takeover page.
        $singleSession->endSameDeviceSessions($user, $deviceId, $this->session->getId());
        $this->session->forget(SingleSession::PENDING_KEY);

        parent::login($user, $remember);
    }

    /** A remember-me cookie never signs a player in while another device holds their session. */
    protected function userFromRecaller($recaller)
    {
        $user = parent::userFromRecaller($recaller);

        $deviceId = DeviceId::from($this->getRequest());

        if ($user !== null && app(SingleSession::class)->signedInElsewhere($user, $this->session->getId(), $deviceId)) {
            $this->viaRemember = false;

            return null;
        }

        return $user;
    }
}
