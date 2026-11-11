<?php

namespace App\Livewire\Wallet;

use App\Account\ResponsiblePlay;
use App\Activity\PlayerActivity;
use App\Facades\KadiApi;
use App\Models\User;
use App\Services\TextSmsService;
use App\Services\WalletBalanceFetcher;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The quick deposit sheet in the site shell (plan Phase 6): an amount, what reaches the wallet after
 * excise duty, and an M-Pesa STK push to the player's confirmed number. Opened by Deposit in the
 * top bar from any page. The same rules as the wallet page: a linked account, a phone confirmed by
 * SMS code (otherwise the phone sheet opens and the deposit resumes after), the minimum, and no
 * deposits during a break. KadiApi still decides (deposit limits included).
 */
class DepositSheet extends Component
{
    public const AMOUNTS = [100, 500, 1000, 2000];

    public bool $open = false;

    public string $amount = '500';

    public ?string $error = null;

    /** sent: the prompt is on the phone and the balance is being watched; received / waiting: the outcome. */
    public ?string $stage = null;

    /** The balance just before the request, fresh from KadiApi; null until one could be read. */
    public ?float $baseline = null;

    public int $polls = 0;

    /** Sent to confirm the phone first: reopen once it is confirmed. */
    public bool $resumeAfterPhone = false;

    #[On('open-deposit')]
    public function show(): void
    {
        $this->error = null;
        $this->stage = null;
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->error = null;
    }

    public function pick(int $amount): void
    {
        if (in_array($amount, self::AMOUNTS, true)) {
            $this->amount = (string) $amount;
            $this->error = null;
        }
    }

    public function send(ResponsiblePlay $responsiblePlay, WalletBalanceFetcher $balances): void
    {
        $user = $this->player();
        $this->error = null;

        if (! $user->linked_id) {
            $this->error = 'Your wallet is still being set up. Try again in a minute.';

            return;
        }

        if (! $user->hasVerifiedPhone()) {
            $this->open = false;
            $this->resumeAfterPhone = true;
            $this->dispatch('open-phone-required', purpose: 'deposit');

            return;
        }

        if ($responsiblePlay->onBreak($user)) {
            $this->error = Index::ON_BREAK_MESSAGE;

            return;
        }

        $amount = (int) round((float) $this->amount);

        if (! is_numeric($this->amount) || $amount < Index::MIN_DEPOSIT) {
            $this->error = 'The smallest deposit is KES '.Index::MIN_DEPOSIT.'.';

            return;
        }

        $remaining = $responsiblePlay->state($user)['deposit_remaining'] ?? null;

        if (is_numeric($remaining) && $amount > (float) $remaining) {
            $this->error = 'Your deposit limit allows KES '.number_format((float) $remaining).' more right now.';

            return;
        }

        // Fresh, not cached: a stale or missing baseline would report a deposit too early, or never.
        $this->baseline = $balances->refresh($user)['balance'];

        $sent = KadiApi::stkDeposit($user, $amount, (string) Str::uuid());
        app(PlayerActivity::class)->record('deposit_requested', $user, ['ok' => $sent]);

        if (! $sent) {
            $this->error = "We couldn't send the M-Pesa request. Try again in a moment.";

            return;
        }

        $this->stage = 'sent';
        $this->polls = 0;
    }

    /** wire:poll while the prompt is on the phone: watch the balance until the money lands. */
    public function check(WalletBalanceFetcher $balances): void
    {
        if ($this->stage !== 'sent') {
            return;
        }

        $this->polls++;
        $balance = $balances->refresh($this->player())['balance'];

        if ($balance !== null && $this->baseline === null) {
            // The balance couldn't be read before the request: the first reading becomes the baseline.
            $this->baseline = $balance;
        } elseif ($balance !== null && $balance > (float) $this->baseline) {
            $this->stage = 'received';
            $this->dispatch('wallet-refreshed');

            return;
        }

        if ($this->polls >= Index::MAX_DEPOSIT_POLLS) {
            $this->stage = 'waiting';
        }
    }

    /** Back after confirming the phone: carry on with the deposit the player started. */
    #[On('phone-saved')]
    public function resume(): void
    {
        if ($this->resumeAfterPhone) {
            $this->resumeAfterPhone = false;
            $this->open = true;
        }
    }

    public function render(): View
    {
        $user = $this->player();

        return view('livewire.wallet.deposit-sheet', [
            'breakdown' => Index::depositBreakdown(is_numeric($this->amount) ? $this->amount : 0),
            'phone' => $user->phone ? TextSmsService::mask($user->phone) : null,
            'amounts' => self::AMOUNTS,
            'min' => Index::MIN_DEPOSIT,
        ]);
    }

    private function player(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
