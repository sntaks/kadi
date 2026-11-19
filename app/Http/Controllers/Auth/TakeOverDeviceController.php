<?php

namespace App\Http\Controllers\Auth;

use App\Activity\PlayerActivity;
use App\Auth\SingleSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Sign the other device out and play here", for a player the one-device rule (App\Auth\SingleSession) just refused.
 * Typical case: the phone's browser was killed during a call and lost its cookie, but its session still counts as live.
 *
 * Only the player whose sign-in checks just passed can use it: the guard parks their id in this session after the
 * password, two-factor, Google or passkey check. Single use and only for SingleSession::TAKEOVER_SECONDS.
 */
class TakeOverDeviceController
{
    public function __invoke(Request $request, SingleSession $singleSession, PlayerActivity $activity): RedirectResponse
    {
        $pending = $singleSession->pullPending($request->session(), fresh: true);

        if ($pending === null) {
            return redirect()->route('login')->withErrors(['email' => 'That took too long. Sign in again.'])->with('signin_form', 'login');
        }

        $user = $pending['user'];
        $singleSession->releaseOtherDevices($user);
        $activity->record('device_taken_over', $user);

        Auth::guard('web')->login($user, $pending['remember']);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home', '/'));
    }
}
