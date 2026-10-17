<div class="space-y-6" wire:poll.30s.visible="pollBalance" wire:init="loadPage">

    {{-- Flash messages --}}
    @if (session('wallet_success'))
        <div class="rounded-lg border border-green-700 bg-green-900/30 p-4 text-sm text-green-400">
            {{ session('wallet_success') }}
        </div>
    @endif

    @if ($purchaseError)
        <div class="rounded-lg border border-red-700 bg-red-900/30 p-4 text-sm text-red-400">
           {{ $purchaseError }}
        </div>
    @endif

    @if ($withdrawError)
        <div class="rounded-lg border border-red-700 bg-red-900/30 p-4 text-sm text-red-400">
           {{ $withdrawError }}
        </div>
    @endif

    @if ($successMessage)
        <div class="rounded-lg border border-green-700 bg-green-900/30 p-4 text-sm text-green-400">
           {{ $successMessage }}
        </div>
    @endif

    {{-- The promo code typed at sign-up was not applied by KadiApi (it can't be added later) --}}
    @if ($this->showPromoNotice)
        <div class="flex items-start justify-between gap-3 rounded-lg border border-amber-600/50 bg-amber-900/20 p-4 text-sm text-amber-300" role="status" data-test="promo-not-applied">
            <span>
                {{ __('Your promo code :code could not be applied when your account was created, so this account will not get the signup bonus.', ['code' => auth()->user()->signup_promo_code]) }}
            </span>
            <button type="button" wire:click="dismissPromoNotice" class="shrink-0 text-amber-300/70 hover:text-amber-200" aria-label="{{ __('Dismiss') }}">
                <flux:icon.x-mark variant="mini" />
            </button>
        </div>
    @endif

    {{-- Title row. The install pill shares this row so it adds no height and stays clear of the money controls. --}}
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-3xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">💰 Vault</h1>
        <div class="flex items-center gap-2">
            <x-pwa.install-button variant="chip" />
            <a href="{{ route('games.history') }}" wire:navigate class="order-first rounded-full border border-yellow-800/40 px-3 py-1 text-xs font-semibold text-[#f5f5f0]/80 transition hover:border-[#f5c542]/60 hover:text-[#f5c542]">Game history</a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Left panel: Balance cards & Actions --}}
        <div class="lg:col-span-1 space-y-4">

            {{-- Card 1: Main Wallet Balance --}}
            <div class="rounded-xl border border-[#f5c542]/40 bg-[#1a1a1a] p-6 shadow-[0_0_30px_rgba(245,197,66,0.08)]">
                <div class="mb-1 text-xs font-semibold uppercase tracking-widest text-[#6b6b6b]">Vault Balance</div>
                @if ($needsLoad)
                    <x-skeleton.region :label="__('Loading your balance…')" class="mb-1 py-1">
                        <x-skeleton class="h-8 w-40 rounded" data-test="wallet-balance-skeleton" />
                    </x-skeleton.region>
                @else
                    <div class="mb-1 text-4xl font-black text-[#f5c542]" style="font-family: 'Cinzel', serif;">
                        {{ $walletCurrencyLabel }} {{ \App\Support\WalletAmount::format($balance) }}
                    </div>
                @endif
                @if ($lockedBonus > 0)
                    <p class="mb-2 text-xs text-amber-300" data-test="locked-bonus-note">
                        {{ __('Signup bonus: KES :amount left to play before you can withdraw it.', ['amount' => number_format($lockedBonus)]) }}
                    </p>
                @endif
                <div class="mb-1 text-xs text-[#6b6b6b]">
                    Account No:
                    @if ($needsLoad)
                        <x-skeleton inline class="h-3 w-20 rounded" />
                    @else
                        <span class="text-[#f5f5f0]/60 font-mono">{{ $kadiCustomer['account_no'] ?? '—' }}</span>
                    @endif
                </div>
                <div class="mb-4 text-xs text-[#6b6b6b]">
                    Player No:
                    @if ($needsLoad)
                        <x-skeleton inline class="h-3 w-20 rounded" />
                    @else
                        <span class="text-[#f5f5f0]/60 font-mono" data-test="wallet-player-no">{{ \App\Support\PlayerNumber::format($kadiCustomer['player_no'] ?? null) ?? '—' }}</span>
                    @endif
                </div>


                <div class="mb-2 h-px bg-yellow-800/20"></div>
                @if($coinsLogicEnabled)
                    <div class="mt-4 space-y-3">
                        <a
                            href="{{ route('buy-coins') }}"
                            wire:navigate
                            class="btn-casino-primary flex w-full items-center justify-center gap-2 rounded-full py-3 text-sm"
                        >
                            + Buy Coins
                        </a>
                        <a
                            href="{{ route('earn-coins') }}"
                            wire:navigate
                            class="btn-casino-ghost flex w-full items-center justify-center gap-2 rounded-full py-3 text-sm"
                        >
                            + Earn Coins
                        </a>
                    </div>
                @endif

                @if($depositWithdrawEnabled)
                    <div class="mt-4 space-y-3">
                        <button
                            wire:click="openDeposit"
                            class="btn-casino-primary flex w-full items-center justify-center gap-2 rounded-full py-3 text-sm"
                        >
                            + Deposit Funds
                        </button>
                        {{-- Disabled until loadPage() has fetched the balance and the locked bonus. --}}
                        <button
                            wire:click="openWithdraw"
                            data-test="open-withdraw" @disabled(! $loaded)
                            class="btn-casino-ghost flex w-full items-center justify-center gap-2 rounded-full py-3 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            - Withdraw Funds
                        </button>
                    </div>

                    <p class="mt-4 text-center text-xs text-[#6b6b6b]">Minimum deposit: KES 10 · Minimum withdrawal: KES 50</p>
                @endif
            </div>

            {{-- Manual M-Pesa Pay Bill load. KadiApi's account_no is the bill ref it matches; the local one is the same value. --}}
            @php
                $paybillAccountNo = $kadiCustomer['account_no'] ?? auth()->user()->account_no;
            @endphp
            @if (filled($paybill) && filled($paybillAccountNo))
                <x-wallet.mpesa-paybill :paybill="$paybill" :account-no="$paybillAccountNo" />
            @endif

            {{-- Card 2: Coins Wallet --}}
            @if($coinsWalletCardEnabled)
                <div class="rounded-xl border border-amber-600/30 bg-[#1a1a1a] p-6 shadow-[0_0_20px_rgba(251,191,36,0.05)]">
                    <div class="mb-1 text-xs font-semibold uppercase tracking-widest text-amber-600/70">Coins Balance</div>
                    <div class="mb-1 text-3xl font-black text-amber-400" style="font-family: 'Cinzel', serif;">
                        {{ number_format($kadiCustomer['coins'] ?? 0, 2) }}
                        <span class="text-base font-semibold text-amber-600/60">Coins</span>
                    </div>
                    <div class="text-xs text-[#6b6b6b]">
                        Coin Wallet ID: <span class="text-[#f5f5f0]/60 font-mono">{{ $kadiCustomer['coin_wallet_id'] ?? '—' }}</span>
                    </div>
                </div>
            @endif

            {{-- Card 3: Load Wallet / Purchases
            <div class="load-wallet-card rounded-xl border border-[#f5c542]/30 bg-[#1a1a1a] p-6">
                <div class="mb-1 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Load Wallet</h3>
                    <span class="stat-badge">
                        <span class="mpesa-dot"></span>
                        M-Pesa
                    </span>
                </div>
                <p class="mb-5 text-xs text-[#6b6b6b]">Tap a pack to pay instantly via M-Pesa STK push.</p>

                <div
                    wire:loading
                    wire:target="initiatePurchase"
                    class="mb-4 flex items-center justify-center gap-2 rounded-lg border border-orange-700 bg-orange-900/30 p-4 text-xs text-orange-400"
                >
                    <span class="animate-spin inline-block">⟳</span> Sending STK push to your phone...
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($purchaseOptions as $index => $option)
                        <button
                            type="button"
                            wire:click="initiatePurchase({{ $index }})"
                            wire:loading.attr="disabled"
                            wire:target="initiatePurchase"
                            class="purchase-tile purchase-tile--{{ $option['type'] === 'coins' ? 'coins' : 'perk' }}"
                        >
                            @if ($option['best'] ?? false)
                                <span class="purchase-tile__ribbon">Best Value</span>
                            @endif

                            <span class="purchase-tile__shine"></span>

                            <span class="purchase-tile__icon-wrap">
                                <span class="purchase-tile__icon">
                                    @if ($option['type'] === 'emoji')
                                        😊
                                    @elseif ($option['type'] === 'gift')
                                        🎁
                                    @else
                                        💰
                                    @endif
                                </span>
                            </span>

                            <span class="purchase-tile__body">
                                @if ($option['type'] === 'emoji')
                                    <span class="purchase-tile__amount">Emojis</span>
                                    <span class="purchase-tile__meta">Pack</span>
                                @elseif ($option['type'] === 'gift')
                                    <span class="purchase-tile__amount">Gifts</span>
                                    <span class="purchase-tile__meta">Pack</span>
                                @else
                                    <span class="purchase-tile__amount">+{{ number_format($option['coins']) }}</span>
                                    <span class="purchase-tile__meta">Coins</span>
                                @endif
                            </span>

                            <span class="purchase-tile__footer">
                                <span class="purchase-tile__price">KSH {{ number_format($option['price']) }}</span>
                                <span class="purchase-tile__arrow">→</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
            --}}
        </div>

        {{-- Right panel: Transaction History --}}
        <div class="lg:col-span-2">
            <div class="rounded-xl border border-yellow-800/30 bg-[#1a1a1a] p-6">
                <h3 class="mb-5 text-xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Transaction History</h3>

                {{-- Filter tabs --}}
                <div class="mb-6 flex gap-2">
                    @foreach (['all' => 'All', 'deposit' => 'Deposits', 'withdrawal' => 'Withdrawals'] as $key => $label)
                        @continue($key === 'withdrawal' && ! $withdrawalsTabEnabled)
                        <button
                            wire:click="setFilter('{{ $key }}')"
                            @class([
                                'rounded-full px-4 py-1.5 text-sm font-semibold transition',
                                'btn-casino-primary'                                                 => $filter === $key,
                                'border border-yellow-800/40 text-[#6b6b6b] hover:text-[#f5f5f0]'  => $filter !== $key,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Tab switch --}}
                <div wire:loading wire:target="setFilter">
                    <x-skeleton.region :label="__('Loading transactions…')">
                        <x-skeleton.rows :count="6" />
                    </x-skeleton.region>
                </div>

                {{-- Table --}}
                <div wire:loading.remove wire:target="setFilter">
                    @if (! $loaded)
                        <x-skeleton.region :label="__('Loading transactions…')">
                            <x-skeleton.rows :count="6" />
                        </x-skeleton.region>
                    @elseif (empty($transactions))
                        <div class="py-16 text-center">
                            <div class="mb-3 text-4xl">🪙</div>
                            <p class="text-[#6b6b6b]">No transactions yet.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-yellow-800/20 text-left text-xs uppercase tracking-wider text-[#6b6b6b]">
                                        <th class="pb-3">Date</th>
                                        <th class="pb-3">Type</th>
                                        <th class="pb-3">Amount</th>
{{--                                        <th class="pb-3">Status</th>--}}
                                        <th class="pb-3">Reference</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-yellow-800/10">
                                    @foreach ($transactions as $tx)
                                        @php
                                            $isWithdrawal = str_contains($tx['payment_type'] ?? '', 'Withdraw');
                                            $status       = (int) ($tx['status'] ?? 0);
                                        @endphp
                                        <tr class="text-[#f5f5f0]/80">
                                            <td class="py-3 text-xs text-[#6b6b6b]">{{ \Illuminate\Support\Carbon::parse($tx['created_at'])->diffForHumans()  ?? '—' }}</td>
                                            <td class="py-3">
                                                @if ($isWithdrawal)
                                                    <span class="rounded-full bg-red-900/50 px-2.5 py-1 text-xs text-red-400 border border-red-700">Withdrawal</span>
                                                @else
                                                    <span class="rounded-full bg-green-900/50 px-2.5 py-1 text-xs text-green-400 border border-green-700">Deposit</span>
                                                @endif
                                            </td>
                                            <td class="py-3 font-semibold {{ $isWithdrawal ? 'text-red-400' : 'text-green-400' }}">
                                                {{ $isWithdrawal ? '-' : '+' }}{{-- session('currency.code', 'KES') --}} {{ number_format($tx['amount'] ?? 0, 2) }}
                                            </td>
                                            {{--
                                            <td class="py-3">
                                                @if ($status === 2)
                                                    <span class="rounded-full bg-green-900/50 px-2.5 py-1 text-xs text-green-400">Completed</span>
                                                @elseif ($status === 1)
                                                    <span class="rounded-full bg-yellow-900/50 px-2.5 py-1 text-xs text-yellow-400">Pending</span>
                                                @else
                                                    <span class="rounded-full bg-red-900/50 px-2.5 py-1 text-xs text-red-400">Failed</span>
                                                @endif
                                            </td>
                                            --}}
                                            <td class="py-3 font-mono text-xs text-[#6b6b6b]">{{ $tx['payment_ref'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>

    {{-- Deposit Modal --}}
    <flux:modal wire:model.self="showDepositModal" class="kadi-bottom-sheet sm:max-w-sm">
        <div class="p-6 space-y-5">
            <div>
                <h3 class="text-xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">💰 Deposit Funds</h3>
                <p class="mt-1 text-sm text-[#6b6b6b]">Confirm the M-Pesa prompt on your phone to complete the deposit.</p>
            </div>

            @if (! $confirmingDeposit)
                {{-- Step 1: amount entry --}}
                <flux:field>
                    <flux:label>Amount (KES)</flux:label>
                    <flux:input
                        type="number"
                        wire:model="depositAmount"
                        min="10"
                        step="1"
                        inputmode="numeric"
                        placeholder="e.g. 100"
                    />
                    <flux:text class="mt-1 text-xs">Minimum deposit: <span class="font-semibold text-[#f5c542]">KES 10</span></flux:text>
                    <flux:error name="depositAmount" />
                </flux:field>

                {{-- Quick amounts --}}
                <div>
                    <p class="mb-2 text-xs uppercase tracking-widest text-[#6b6b6b]">Quick amounts</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([20, 50, 100, 500, 1000, 5000] as $quick)
                            <button
                                type="button"
                                wire:click="$set('depositAmount', '{{ $quick }}')"
                                @class([
                                    'rounded-full border px-4 py-1.5 text-sm font-semibold transition',
                                    'border-[#f5c542]/25 text-[#f5f5f0]/80 hover:border-[#f5c542]/60 hover:bg-[#f5c542]/10' => (float) $depositAmount !== (float) $quick,
                                    'border-[#f5c542] bg-[#f5c542]/15 text-[#f5c542]' => (float) $depositAmount === (float) $quick,
                                ])
                            >
                                {{ number_format($quick) }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Excise duty notice: recalculated client-side on every keystroke. --}}
                <div
                    x-data="{
                        rate: {{ \App\Livewire\Wallet\Index::EXCISE_DUTY_RATE }},
                        get amount() {
                            const n = Math.round(parseFloat($wire.depositAmount));
                            return Number.isFinite(n) && n > 0 ? n : 0;
                        },
                        get excise() { return Math.round(this.amount * this.rate * 100) / 100; },
                        get credited() { return Math.round((this.amount - this.excise) * 100) / 100; },
                        kes(v) { return 'KES ' + v.toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                    }"
                    class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
                    role="status"
                    aria-live="polite"
                    data-test="deposit-excise-notice"
                >
                    <p class="flex items-start gap-2 font-medium text-amber-300">
                        <flux:icon.information-circle variant="mini" class="mt-0.5 shrink-0" />
                        <span>A {{ \App\Livewire\Wallet\Index::EXCISE_DUTY_RATE * 100 }}% excise duty is deducted from every deposit. {{ (1 - \App\Livewire\Wallet\Index::EXCISE_DUTY_RATE) * 100 }}% is loaded into your wallet.</span>
                    </p>

                    <dl x-show="amount > 0" x-cloak class="mt-3 space-y-1 border-t border-amber-500/20 pt-3">
                        <div class="flex justify-between text-[#f5f5f0]/80">
                            <dt>You pay</dt>
                            <dd class="font-semibold" x-text="kes(amount)"></dd>
                        </div>
                        <div class="flex justify-between text-red-300">
                            <dt>Excise duty ({{ \App\Livewire\Wallet\Index::EXCISE_DUTY_RATE * 100 }}%)</dt>
                            <dd class="font-semibold" x-text="'− ' + kes(excise)"></dd>
                        </div>
                        <div class="flex justify-between text-green-400">
                            <dt>Loaded into wallet</dt>
                            <dd class="font-bold" x-text="kes(credited)"></dd>
                        </div>
                    </dl>
                </div>

                @if ($depositError)
                    <p class="text-sm font-medium text-red-400" role="alert">{{ $depositError }}</p>
                @endif

                <div class="flex gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full">Cancel</flux:button>
                    </flux:modal.close>

                    <flux:button
                        variant="primary"
                        class="w-full"
                        wire:click="requestDeposit"
                        wire:loading.attr="disabled"
                        wire:target="requestDeposit"
                    >
                        Deposit
                    </flux:button>
                </div>
            @else
                {{-- Step 2: confirmation --}}
                <div class="space-y-1 rounded-xl border border-[#f5c542]/30 bg-[#f5c542]/5 p-4 text-center">
                    <p class="text-xs uppercase tracking-widest text-[#6b6b6b]">You are about to deposit</p>
                    <p class="text-3xl font-black text-[#f5c542]" style="font-family: 'Cinzel', serif;">
                        KES {{ number_format((float) $depositAmount) }}
                    </p>
                    <p class="text-xs text-[#6b6b6b]">
                        via M-Pesa to <span class="font-semibold text-[#f5f5f0]/80">{{ $this->mpesaPhone ?? 'your registered number' }}</span>
                    </p>
                </div>

                @php($breakdown = \App\Livewire\Wallet\Index::depositBreakdown($depositAmount))
                <dl class="space-y-1 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm" data-test="deposit-excise-breakdown">
                    <div class="flex justify-between text-red-300">
                        <dt>Excise duty ({{ \App\Livewire\Wallet\Index::EXCISE_DUTY_RATE * 100 }}%)</dt>
                        <dd class="font-semibold">− KES {{ number_format($breakdown['excise'], 2) }}</dd>
                    </div>
                    <div class="flex justify-between text-green-400">
                        <dt>Loaded into wallet</dt>
                        <dd class="font-bold">KES {{ number_format($breakdown['credited'], 2) }}</dd>
                    </div>
                </dl>

                <div class="flex gap-2">
                    <flux:button
                        variant="ghost"
                        class="w-full"
                        wire:click="cancelDeposit"
                        wire:loading.attr="disabled"
                        wire:target="confirmDeposit"
                    >
                        Back
                    </flux:button>

                    <flux:button
                        variant="primary"
                        class="w-full"
                        wire:click="confirmDeposit"
                        :disabled="$processingDeposit"
                        wire:loading.attr="disabled"
                        wire:target="confirmDeposit"
                    >
                        {{ $processingDeposit ? 'Sending…' : 'Confirm Deposit' }}
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>

    {{-- Withdraw Modal --}}
    <flux:modal wire:model.self="showWithdrawModal" class="kadi-bottom-sheet sm:max-w-sm">
        <div class="p-6 space-y-5">
            <div>
                <h3 class="text-xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">💸 Withdraw Funds</h3>
                <p class="mt-1 text-sm text-[#6b6b6b]">
                    Available:
                    @if ($loaded)
                        <span class="font-semibold text-[#f5c542]" data-test="withdrawable">{{ $walletCurrencyLabel }} {{ number_format(floor($this->withdrawableBalance)) }}</span>
                    @else
                        <x-skeleton inline class="h-4 w-16 rounded" />
                    @endif
                </p>
                @if ($lockedBonus > 0)
                    <p class="mt-1 text-xs text-amber-300">
                        {{ __('KES :amount of signup bonus has to be played first.', ['amount' => number_format($lockedBonus)]) }}
                    </p>
                @endif
            </div>

            @if (! $confirmingWithdraw)
                {{-- Step 1: amount entry --}}
                <flux:field>
                    <flux:label>Amount (KES)</flux:label>
                    <flux:input
                        type="number"
                        wire:model="withdrawAmount"
                        min="50"
                        max="{{ (int) floor($this->withdrawableBalance) }}"
                        step="1"
                        inputmode="numeric"
                        placeholder="Min KES 50"
                    />
                    <flux:text class="mt-1 text-xs">Minimum withdrawal: <span class="font-semibold text-[#f5c542]">KES 50</span></flux:text>
                    <flux:error name="withdrawAmount" />
                </flux:field>

                @if ($withdrawError)
                    <p class="text-sm font-medium text-red-400" role="alert">{{ $withdrawError }}</p>
                @endif

                <div class="flex gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full">Cancel</flux:button>
                    </flux:modal.close>

                    <flux:button
                        variant="primary"
                        class="w-full"
                        wire:click="requestWithdraw"
                        wire:loading.attr="disabled"
                        wire:target="requestWithdraw"
                    >
                        Withdraw
                    </flux:button>
                </div>
            @else
                {{-- Step 2: confirmation — only reachable with a valid amount --}}
                <div class="space-y-1 rounded-xl border border-[#f5c542]/30 bg-[#f5c542]/5 p-4 text-center">
                    <p class="text-xs uppercase tracking-widest text-[#6b6b6b]">You are about to withdraw</p>
                    <p class="text-3xl font-black text-[#f5c542]" style="font-family: 'Cinzel', serif;">
                        KES {{ number_format((float) $withdrawAmount) }}
                    </p>
                    <p class="text-xs text-[#6b6b6b]">
                        paid out by M-Pesa to your registered number
                        <span class="font-semibold text-[#f5f5f0]/80">{{ $this->mpesaPhone }}</span>
                    </p>
                </div>

                <div class="flex gap-2">
                    <flux:button
                        variant="ghost"
                        class="w-full"
                        wire:click="cancelWithdraw"
                        wire:loading.attr="disabled"
                        wire:target="confirmWithdraw"
                    >
                        Back
                    </flux:button>

                    <flux:button
                        variant="primary"
                        class="w-full"
                        wire:click="confirmWithdraw"
                        :disabled="$processingWithdraw"
                        wire:loading.attr="disabled"
                        wire:target="confirmWithdraw"
                    >
                        {{ $processingWithdraw ? 'Processing…' : 'Confirm Withdrawal' }}
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>

    {{-- Pending deposit: hard-refresh the balance until M-Pesa confirms it. --}}
    @if ($awaitingDeposit)
        <div wire:poll.5s="checkDepositStatus" class="hidden" aria-hidden="true"></div>
    @endif

</div>
