@auth
@if ($variant === 'pill')
    {{-- Site shell: one pill with the balance (opens the wallet) and Deposit. Same loading, poll and real-time paths. --}}
    <div
        @if($needsLoad) wire:init="loadBalance" @endif
        wire:poll.30s.visible="pollBalance"
        x-on:visibilitychange.window="if (! document.hidden) $wire.pollBalance()"
        class="nt-wallet"
        data-test="wallet-pill"
    >
        <a href="{{ route('wallet') }}" wire:navigate class="nt-bal nt-num" title="{{ __('Open your wallet') }}">
            @if ($hasError)
                <span class="nt-bal-note"><span class="max-[640px]:sr-only">{{ __('Balance unavailable') }}</span><span class="min-[641px]:hidden" aria-hidden="true">—</span></span>
            @elseif ($balance !== null)
                <small>KES</small>{{ \App\Support\WalletAmount::format($balance) }}
            @elseif (! $needsLoad)
                <span class="nt-bal-note">—</span>
            @else
                <span class="sr-only">{{ __('Loading balance…') }}</span>
                <x-skeleton inline class="h-4 w-14 rounded" data-test="balance-skeleton" />
            @endif
        </a>
        <x-ui.button size="sm" :href="route('wallet')" wire:navigate>{{ __('Deposit') }}</x-ui.button>
    </div>
@else
    <div
        @if($needsLoad) wire:init="loadBalance" @endif
        wire:poll.30s.visible="pollBalance"
        x-data="{ cooldown: false }"
        x-on:visibilitychange.window="if (! document.hidden) $wire.pollBalance()"
        class="flex items-center gap-2"
    >
        {{-- Balance pill — links to wallet page --}}
        <a
            href="{{ route('wallet') }}"
            wire:navigate
            class="relative flex h-10 items-center gap-2 rounded-full border border-yellow-800/30 px-4 transition hover:border-[#f5c542]/50"
            title="{{ __('Wallet balance') }}"
        >
            {{-- Placeholder while a fetch is running: hidden by default --}}
            <span
                class="hidden"
                wire:loading.class.remove="hidden"
                wire:target="loadBalance, refreshWallet"
            >
                <span class="sr-only">{{ __('Loading balance…') }}</span>
                <x-skeleton inline class="h-4 w-14 rounded" />
            </span>

            {{-- Balance content: visible by default, hidden while loading --}}
            <span
                class="flex items-center gap-1.5"
                wire:loading.class="hidden"
                wire:target="loadBalance, refreshWallet"
            >
                <span class="text-sm leading-none select-none" aria-hidden="true">💰</span>

                <span class="text-sm font-semibold text-[#f5c542]" style="font-family: 'Cinzel', serif;">
                    @if ($hasError)
                        <span class="text-xs font-normal tracking-normal text-[#f5f5f0]/40">{{ __('unavailable') }}</span>
                    @elseif ($balance !== null)
                        {{ \App\Support\WalletAmount::format($balance) }}
                    @elseif ($needsLoad)
                        <span class="sr-only">{{ __('Loading balance…') }}</span>
                        <x-skeleton inline class="h-4 w-14 rounded" data-test="balance-skeleton" />
                    @else
                        <span class="font-normal text-[#f5f5f0]/30">—</span>
                    @endif
                </span>
            </span>
        </a>

        {{-- Refresh — mirrors the notifications-bell button styling --}}
        <button
            type="button"
            x-on:click="
                if (cooldown) return;
                cooldown = true;
                setTimeout(() => cooldown = false, 10000);
                $wire.refreshWallet();
            "
            :disabled="cooldown"
            :title="cooldown ? '{{ __('Please wait…') }}' : '{{ __('Refresh balance') }}'"
            aria-label="{{ __('Refresh wallet balance') }}"
            class="relative flex h-10 w-10 items-center justify-center rounded-full border border-yellow-800/30 text-[#f5f5f0]/70 transition hover:border-[#f5c542]/50 hover:text-[#f5c542] disabled:cursor-not-allowed disabled:opacity-40"
            :class="cooldown && 'opacity-40 cursor-not-allowed'"
        >
            <svg
                wire:loading.class="animate-spin text-[#f5c542]"
                wire:target="refreshWallet"
                class="size-5"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
        </button>
    </div>
@endif
@endauth
