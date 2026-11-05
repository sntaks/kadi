<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleLinkController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('account')
                ->withErrors(['google' => 'Google authentication failed. Please try again.']);
        }

        // Block if another user already owns this google_id
        $conflict = User::where('google_id', $googleUser->getId())
            ->where('id', '!=', Auth::id())
            ->exists();

        if ($conflict) {
            return redirect()->route('account')
                ->withErrors(['google' => 'This Google account is already linked to another user.']);
        }

        $updateData = [
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
        ];

        if (is_null(Auth::user()->email_verified_at)) {
            $updateData['email_verified_at'] = now();
        }

        Auth::user()->update($updateData);

        return redirect()->route('account')
            ->with('status', 'google-linked');
    }
}
