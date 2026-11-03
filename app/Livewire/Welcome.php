<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kadi Online — Play the Kenyan Kadi Card Game')]
class Welcome extends Component
{
    /**
     * Players online, from the game server. Fresh for 5 minutes, then served stale for up to 15
     * while it refreshes after the response is sent, so no visitor waits on the game server. A
     * failed refresh keeps the last good count instead of dropping to 0.
     */
    public function livePlayers(): int
    {
        return (int) Cache::flexible('kadi.live_players', [300, 900], function () {
            try {
                $response = Http::timeout(3)
                    ->get('https://gameapi.kadi.online/kadi/get_user_totals.php')
                    ->throw()
                    ->json();

                $total = ($response['jackpots']['total'] ?? 0)
                    + ($response['single']['total'] ?? 0)
                    + ($response['tournaments']['total'] ?? 0);

                Cache::forever('kadi.live_players.last', $total);

                return $total;
            } catch (ConnectionException|RequestException $e) {
                Log::error('Welcome: Failed to fetch live players: '.$e->getMessage());

                return (int) Cache::get('kadi.live_players.last', 0);
            }
        });
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        $user = auth()->user();

        return $user && $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail();
    }

    public function resendVerificationNotification(): void
    {
        $user = auth()->user();

        if (! $user || $user->hasVerifiedEmail()) {
            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    public function render(): Factory|\Illuminate\Contracts\View\View|View
    {
        return view('livewire.welcome', [
            'livePlayers' => $this->livePlayers(),
            'users' => User::count(),
        ])
            ->layout('layouts.guest')
            ->layoutData([
                'description' => 'Play Kadi online, the Kenyan Kadi card game. Free to join, real opponents, tournaments with prize pools and M-Pesa deposits. Sign up and play Kadi today.',
                'page' => 'home',
            ]);
    }
}
