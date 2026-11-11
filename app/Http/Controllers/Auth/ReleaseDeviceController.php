<?php

namespace App\Http\Controllers\Auth;

use App\Activity\PlayerActivity;
use App\Auth\SingleSession;
use App\Listeners\EndGameSessionsOnLogout;
use App\Mail\ReleaseDeviceEmail;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * "Sign out my other device" for a player refused by the one-device rule (App\Auth\SingleSession), e.g. after losing
 * the phone that is still signed in. Only a player who just passed the sign-in checks can ask (their id is parked in
 * the session by the guard), and the link goes to the account's own e-mail address: single use, 15 minutes.
 */
class ReleaseDeviceController
{
    public const LINK_MINUTES = 15;

    private const STATUS_SENT = "We've e-mailed you a link that signs your other device out. It works once, for 15 minutes.";

    /** Sends the link. Answers the same whatever happened, so it never says whether an account exists. */
    public function send(Request $request): RedirectResponse
    {
        $userId = $request->session()->pull(SingleSession::PENDING_KEY);
        $user = $userId !== null ? User::find($userId) : null;

        if ($user !== null) {
            $nonce = Str::random(48);
            Cache::put($this->cacheKey($nonce), $user->id, now()->addMinutes(self::LINK_MINUTES));

            $url = URL::temporarySignedRoute('login.release-device.confirm', now()->addMinutes(self::LINK_MINUTES), ['nonce' => $nonce]);
            Mail::to($user->email)->queue(new ReleaseDeviceEmail($user, $url, self::LINK_MINUTES));
        }

        return redirect()->route('login')->with('status', self::STATUS_SENT);
    }

    /** Opens the e-mailed link: ends every session the player has and retires their remember-me cookies. */
    public function confirm(Request $request, string $nonce): RedirectResponse
    {
        $userId = Cache::pull($this->cacheKey($nonce));
        $user = $userId !== null ? User::find($userId) : null;

        if ($user === null) {
            return redirect()->route('login')->withErrors(['email' => 'That link has expired or was already used. Try signing in again.']);
        }

        app(SingleSession::class)->endOtherSessions($user);
        $user->setRememberToken(Str::random(60));
        $user->save();

        // The released device is signed out, so its games and sports token end too, as on any logout.
        app(EndGameSessionsOnLogout::class)->handle(new Logout('web', $user));
        app(PlayerActivity::class)->record('other_device_released', $user);

        return redirect()->route('login')->with('status', 'Your other device is signed out. You can sign in now.');
    }

    private function cacheKey(string $nonce): string
    {
        return 'release-device:'.hash('sha256', $nonce);
    }
}
