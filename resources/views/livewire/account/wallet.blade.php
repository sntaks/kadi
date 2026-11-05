{{--
    The wallet in the account area (plan Phase 6): App\Livewire\Wallet\Index, its rules unchanged. Deposits
    open the shell's deposit sheet; withdrawals keep their two steps here.
--}}
<x-account.frame active="wallet">
    <div class="nt-area" wire:poll.30s.visible="pollBalance" wire:init="loadPage">
        <div class="nt-stack">
            @foreach (array_filter([session('wallet_success'), $successMessage]) as $message)
                <p class="nt-ok" role="status">{{ $message }}</p>
            @endforeach
            @foreach (array_filter([$withdrawError && ! $showWithdrawModal ? $withdrawError : null]) as $message)
                <p class="nt-error" role="alert">{{ $message }}</p>
            @endforeach

            @if ($this->showPromoNotice)
                <div class="nt-callout" role="status" data-test="promo-not-applied">
                    <span>{{ __('Your promo code :code could not be applied when your account was created, so this account will not get the signup bonus.', ['code' => auth()->user()->signup_promo_code]) }}</span>
                    <x-ui.icon-button label="Dismiss" wire:click="dismissPromoNotice"><x-site.icon name="x" /></x-ui.icon-button>
                </div>
            @endif

            {{-- Balance and the two money actions --}}
            <section class="nt-panel nt-card-pad" aria-labelledby="wallet-title">
                <h2 id="wallet-title" class="nt-card-title">Wallet</h2>
                <div class="mt-3 flex flex-wrap items-end gap-x-8 gap-y-3">
                    <div class="nt-stat nt-stat-gold">
                        <span>Balance</span>
                        @if ($needsLoad)
                            <x-skeleton class="h-8 w-40 rounded" data-test="wallet-balance-skeleton" />
                        @else
                            <b class="nt-num text-[30px]">{{ $walletCurrencyLabel }} {{ \App\Support\WalletAmount::format($balance) }}</b>
                        @endif
                    </div>
                    <div class="nt-stat">
                        <span>Player no.</span>
                        @if ($needsLoad)<x-skeleton class="h-5 w-20 rounded" />@else<b class="nt-num text-[16px]" data-test="wallet-player-no">{{ \App\Support\PlayerNumber::format($kadiCustomer['player_no'] ?? null) ?? '—' }}</b>@endif
                    </div>
                </div>
                @if ($lockedBonus > 0)
                    <p class="nt-hint mt-2" data-test="locked-bonus-note">{{ __('Signup bonus: KES :amount left to play before you can withdraw it.', ['amount' => number_format($lockedBonus)]) }}</p>
                @endif

                @if ($depositWithdrawEnabled)
                    <div class="mt-5 flex flex-wrap gap-2">
                        <x-ui.button x-on:click="$dispatch('open-deposit')" aria-haspopup="dialog">Deposit</x-ui.button>
                        <x-ui.button variant="ghost" wire:click="openWithdraw" data-test="open-withdraw" :disabled="! $loaded">Withdraw</x-ui.button>
                    </div>
                    <p class="nt-hint mt-3">Smallest deposit KES {{ \App\Livewire\Wallet\Index::MIN_DEPOSIT }} · smallest withdrawal KES 50</p>
                @endif
            </section>

            {{-- Transactions --}}
            <section class="nt-panel" aria-labelledby="tx-title">
                <div class="nt-list-head">
                    <h2 id="tx-title">Transactions</h2>
                    <div class="nt-tabs" role="tablist" aria-label="Show">
                        @foreach (['all' => 'All', 'deposit' => 'Deposits', 'withdrawal' => 'Withdrawals'] as $key => $label)
                            @continue($key === 'withdrawal' && ! $withdrawalsTabEnabled)
                            <button type="button" role="tab" class="nt-tab" wire:click="setFilter('{{ $key }}')" aria-selected="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <div wire:loading wire:target="setFilter" class="block px-[22px] pb-4">
                    <x-skeleton.region :label="__('Loading transactions…')"><x-skeleton.rows :count="6" /></x-skeleton.region>
                </div>
                <div wire:loading.remove wire:target="setFilter">
                    @if (! $loaded)
                        <div class="px-[22px] pb-4"><x-skeleton.region :label="__('Loading transactions…')"><x-skeleton.rows :count="6" /></x-skeleton.region></div>
                    @elseif (empty($transactions))
                        <div class="nt-empty px-[22px]"><b>No transactions yet</b>Deposits and withdrawals show up here.</div>
                    @else
                        <ul class="nt-list">
                            @foreach ($transactions as $tx)
                                @php($out = str_contains($tx['payment_type'] ?? '', 'Withdraw'))
                                <li class="nt-row">
                                    <span class="nt-row-icon" aria-hidden="true"><x-site.icon :name="$out ? 'out' : 'down'" /></span>
                                    <span class="min-w-0">
                                        <b>{{ $out ? 'Withdrawal' : 'Deposit' }}</b>
                                        <span class="nt-meta">{{ \Illuminate\Support\Carbon::parse($tx['created_at'])->diffForHumans() }} · {{ $tx['payment_ref'] ?? '—' }}</span>
                                    </span>
                                    <span @class(['nt-trail nt-num', 'nt-in' => ! $out])>{{ $out ? '−' : '+' }}KES {{ number_format($tx['amount'] ?? 0, 2) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>

        <aside class="nt-stack" aria-label="Other ways to pay">
            @php($paybillAccountNo = $kadiCustomer['account_no'] ?? auth()->user()->account_no)
            @if (filled($paybill) && filled($paybillAccountNo))
                <x-wallet.mpesa-paybill :paybill="$paybill" :account-no="$paybillAccountNo" />
            @endif
            <x-pwa.install-button variant="chip" />
        </aside>
    </div>

    {{-- Withdraw: amount, then confirm. Same component state as before; a sheet in the Night table look. --}}
    <div x-data="{ open: $wire.entangle('showWithdrawModal') }" x-on:keydown.escape.window="open = false">
        <div class="nt-scrim" x-show="open" x-cloak x-transition.opacity x-on:click="open = false"></div>
        <div role="dialog" aria-modal="true" aria-labelledby="withdraw-title" class="nt-sheet"
             x-show="open" x-cloak x-trap.inert.noscroll="open"
             x-transition:enter="nt-sheet-anim" x-transition:enter-start="nt-sheet-from" x-transition:enter-end=""
             x-transition:leave="nt-sheet-anim" x-transition:leave-start="" x-transition:leave-end="nt-sheet-from">
            <button type="button" class="nt-icon-btn nt-sheet-close" aria-label="Close" x-on:click="open = false"><x-site.icon name="x" /></button>
            <div class="nt-sheet-inner grid gap-4">
                <div>
                    <h2 id="withdraw-title">Withdraw to M-Pesa</h2>
                    <p class="nt-hint">Available:
                        @if ($loaded)<b class="nt-num text-gold" data-test="withdrawable">{{ $walletCurrencyLabel }} {{ number_format(floor($this->withdrawableBalance)) }}</b>@else<x-skeleton inline class="h-4 w-16 rounded" />@endif
                    </p>
                    @if ($lockedBonus > 0)<p class="nt-hint mt-1">{{ __('KES :amount of signup bonus has to be played first.', ['amount' => number_format($lockedBonus)]) }}</p>@endif
                </div>

                @if (! $confirmingWithdraw)
                    <form wire:submit="requestWithdraw" class="grid gap-4">
                        <label class="nt-field">
                            <span>Amount (KES)</span>
                            <input type="text" inputmode="numeric" wire:model="withdrawAmount" placeholder="At least KES 50" autocomplete="off">
                        </label>
                        @error('withdrawAmount')<p class="nt-error">{{ $message }}</p>@enderror
                        @if ($withdrawError)<p class="nt-error" role="alert">{{ $withdrawError }}</p>@endif
                        <x-ui.button type="submit" size="lg" block wire:loading.attr="disabled" wire:target="requestWithdraw">Continue</x-ui.button>
                    </form>
                @else
                    <div class="nt-deposit-state text-center">
                        <p class="nt-hint">You're withdrawing</p>
                        <p class="nt-num text-[28px] font-extrabold text-gold">KES {{ number_format((float) $withdrawAmount) }}</p>
                        <p class="nt-hint">paid by M-Pesa to {{ $this->mpesaPhone }}</p>
                    </div>
                    <div class="flex gap-2">
                        <x-ui.button variant="ghost" class="flex-1" wire:click="cancelWithdraw" wire:loading.attr="disabled" wire:target="confirmWithdraw">Back</x-ui.button>
                        <x-ui.button class="flex-1" wire:click="confirmWithdraw" :disabled="$processingWithdraw" wire:loading.attr="disabled" wire:target="confirmWithdraw">
                            {{ $processingWithdraw ? 'Sending…' : 'Confirm withdrawal' }}
                        </x-ui.button>
                    </div>
                @endif
            </div>
        </div>
    </div>

</x-account.frame>
