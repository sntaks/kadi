<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\ConsentValidationRules;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RememberReturnPath;
use App\Jobs\ProcessVerifiedUser;
use App\Models\User;
use App\Promotions\PromoCode;
use App\Referrals\ReferralCode;
use App\Support\AuthView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    use ConsentValidationRules;

    public function redirect(Request $request)
    {
        // From the sign-in sheet: come back to the page it opened on (same-site paths only).
        RememberReturnPath::remember($request, $request->query('return_to'));

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Google authentication failed. Please try again.']);
        }

        // Case a: existing user matched by google_id — already processed, just log in
        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user) {
            $this->markVerified($user);

            return $this->finalizeLogin($request, $user);
        }

        // Case b: existing user matched by email — link the Google account.
        // Dispatch ProcessVerifiedUser in case this user never verified their email
        // (and therefore never triggered the job through the normal Verified event).
        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $updateData = [
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ];

            if (is_null($user->email_verified_at)) {
                $updateData['email_verified_at'] = now();
            }

            $user->update($updateData);

            if (! $user->isLinked()) {
                ProcessVerifiedUser::dispatch($user);
            }

            return $this->finalizeLogin($request, $user);
        }

        // Case c: brand-new Google user. Google gives us neither age nor terms
        // acceptance, so park the verified identity in the session and create
        // the account only once the user confirms 18+ and accepts the terms.
        $request->session()->put('google.pending', [
            'id' => $googleUser->getId(),
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'avatar' => $googleUser->getAvatar(),
            'expires_at' => now()->addMinutes(15)->getTimestamp(),
        ]);

        return redirect()->route('auth.google.complete');
    }

    public function complete(Request $request): View|RedirectResponse
    {
        $pending = $this->pendingGoogleUser($request);

        if (! $pending) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your sign-up session expired. Please try again.']);
        }

        return view(AuthView::name('google-complete'), ['name' => $pending['name']]);
    }

    public function storeComplete(Request $request): RedirectResponse
    {
        $pending = $this->pendingGoogleUser($request);

        if (! $pending) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your sign-up session expired. Please try again.']);
        }

        $request->merge([
            'referral_code' => ReferralCode::normalize($request->input('referral_code')),
            'promo_code' => PromoCode::normalize($request->input('promo_code')),
        ]);
        $request->validate(
            [...$this->consentRules(), 'referral_code' => ReferralCode::signupRules(), 'promo_code' => PromoCode::signupRules()],
            [...$this->consentMessages(), ...ReferralCode::signupMessages(), ...PromoCode::signupMessages()],
        );

        // The account could have been created between the callback and now.
        if (User::where('email', $pending['email'])->orWhere('google_id', $pending['id'])->exists()) {
            $request->session()->forget('google.pending');

            return redirect()->route('login')
                ->withErrors(['email' => 'An account with this email already exists. Please sign in.']);
        }

        // No plain-password cache entry; ProcessVerifiedUser handles null password gracefully.
        $user = new User([
            'name' => $pending['name'],
            'email' => $pending['email'],
            'google_id' => $pending['id'],
            'avatar' => $pending['avatar'],
            'password' => Hash::make(Str::random(32)),
            'account_no' => 'KK-'.strtoupper(uniqid()),
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            ...User::consentAttributes(),
            // Sent as referral_code on POST customers by ProcessVerifiedUser.
            'signup_referral_code' => config('kadi.referrals.enabled') ? $request->input('referral_code') : null,
            'signup_promo_code' => PromoCode::enabled() ? $request->input('promo_code') : null,
        ])->save();

        $request->session()->forget('google.pending');
        ReferralCode::forget($request);
        PromoCode::forget($request);

        ProcessVerifiedUser::dispatch($user);

        return $this->finalizeLogin($request, $user);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('google.pending');
        // A cancelled Google sign-up must not send a later, unrelated login to the page it started on.
        RememberReturnPath::forget($request);

        return redirect()->route('register');
    }

    /**
     * @return array{id: string, name: string, email: string, avatar: ?string}|null
     */
    private function pendingGoogleUser(Request $request): ?array
    {
        $pending = $request->session()->get('google.pending');

        if (! $pending || $pending['expires_at'] < now()->getTimestamp()) {
            $request->session()->forget('google.pending');

            return null;
        }

        return $pending;
    }

    private function markVerified(User $user): void
    {
        if (is_null($user->email_verified_at)) {
            $user->update(['email_verified_at' => now()]);
        }
    }

    /**
     * Complete the Google sign-in, enforcing two-factor authentication
     * exactly like Fortify's password login flow does.
     */
    private function finalizeLogin(Request $request, User $user): RedirectResponse
    {
        if ($user->hasEnabledTwoFactorAuthentication()) {
            // Park the user behind the two-factor challenge. No authenticated
            // session and no remember token exist until a valid code passes.
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, remember: false);

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}
