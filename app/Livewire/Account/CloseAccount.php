<?php

namespace App\Livewire\Account;

use App\Activity\PlayerActivity;
use App\Facades\KadiApi;
use App\Jobs\RevokeGameSessions;
use App\Livewire\Actions\Logout;
use App\Models\User;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Wagers\WagerOps;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Close my account (Security tab of /account). KadiApi decides first: it refuses while the player holds
 * money or has a game, tournament or withdrawal in progress, and soft-deletes the customer otherwise.
 * Only then is the local account deleted and the player logged out. Breaks and limits stay with
 * KadiApi, which applies them again to a new account with the same phone or ID.
 */
class CloseAccount extends Component
{
    public const CONFIRM_WORD = 'DELETE';

    /** What KadiApi's `open` reasons mean for the player. */
    public const REASONS = [
        'wallet_balance' => 'Withdraw or use up your wallet balance first.',
        'referral_balance' => 'Withdraw your Invite & Earn balance first.',
        'coin_balance' => 'Use up your coins first.',
        'pending_withdrawal' => 'Wait for your withdrawal to finish first.',
        'pending_referral_withdrawal' => 'Wait for your Invite & Earn withdrawal to finish first.',
        'open_game' => 'Finish the Kadi game you are in first.',
        'open_competition' => 'Finish the tournament or jackpot you joined first.',
        'open_sports_bet' => 'Wait for your sports bets and jackpot entries to settle first.',
    ];

    public bool $confirming = false;

    public string $password = '';

    public string $confirmWord = '';

    /** @var list<string> */
    public array $blockers = [];

    public ?string $error = null;

    public function start(): void
    {
        $this->reset('password', 'confirmWord', 'blockers', 'error');
        $this->confirming = true;
    }

    public function cancel(): void
    {
        $this->reset('confirming', 'password', 'confirmWord', 'blockers', 'error');
    }

    public function close(Logout $logout): void
    {
        $user = $this->player();
        $this->error = null;
        $this->blockers = [];

        if (! RateLimiter::attempt('close-account:'.$user->id, 5, fn () => true, 300)) {
            $this->error = 'Too many tries. Wait a few minutes and try again.';

            return;
        }

        if ($this->needsPassword($user) && ! Hash::check($this->password, $user->password)) {
            $this->addError('password', 'That password is not right.');

            return;
        }

        if (strtoupper(trim($this->confirmWord)) !== self::CONFIRM_WORD) {
            $this->addError('confirmWord', 'Type '.self::CONFIRM_WORD.' to confirm.');

            return;
        }

        // The Kadi API does not know which sports bets are still to settle here: refuse before asking it.
        if ($this->hasUnsettledSportsBets($user)) {
            $this->blockers = [self::REASONS['open_sports_bet']];

            return;
        }

        if ($user->linked_id) {
            try {
                $result = KadiApi::deleteCustomer((int) $user->linked_id);
            } catch (\Throwable $e) {
                Log::warning('Close account: KadiApi unavailable', ['user' => $user->id, 'error' => class_basename($e)]);
                $this->error = "We couldn't close your account just now. Nothing was changed; try again in a moment.";

                return;
            }

            if (! $result['deleted']) {
                $this->blockers = array_values(array_unique(array_map(
                    fn (string $reason) => self::REASONS[$reason] ?? 'Something on your account is still in progress. Contact support and we\'ll help.',
                    $result['open'] === [] ? ['unknown'] : $result['open'],
                )));

                return;
            }

            RevokeGameSessions::dispatch((int) $user->linked_id);
        }

        Log::info('Account closed by the player', ['user' => $user->id]);
        app(PlayerActivity::class)->record('account_closed', $user);

        tap($user, $logout(...))->delete();

        $this->redirect('/', navigate: false);
    }

    /**
     * Sports bets or jackpot entries not settled yet, or whose settlement has not reached the API: the history stays
     * (without the player) once the account is closed, but money still owed must move first.
     */
    private function hasUnsettledSportsBets(User $user): bool
    {
        return Bet::query()->where('user_id', $user->id)->whereIn('status', [BetStatus::Pending, BetStatus::Open])->exists()
            || JackpotEntry::query()->where('user_id', $user->id)->whereIn('status', [JackpotEntryStatus::Pending, JackpotEntryStatus::Open])->exists()
            || app(WagerOps::class)->hasUnsentFor($user);
    }

    /** Players who signed up with Google may never have set a password: typing the word is their confirmation. */
    public function needsPassword(User $user): bool
    {
        return $user->google_id === null;
    }

    public function render(): View
    {
        return view('livewire.account.close-account', ['needsPassword' => $this->needsPassword($this->player())]);
    }

    private function player(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
