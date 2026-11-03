{{--
    The account area's shared header and sub-tabs (plan Phase 6): Account, Wallet, Activity, Invite & Earn
    and Responsible play read as one area. `avatar` is the player's picture URL when the page has it.
    `active` is the current tab's route: the frame re-renders on Livewire updates, whose route is livewire.update.
--}}
@props(['avatar' => null, 'active'])
@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $nav = \App\Support\SiteNavigation::current();
    $balance = app(\App\Services\WalletBalanceFetcher::class)->cached($user);
@endphp
<div {{ $attributes->class('nt-view-in') }}>
    <header class="nt-panel nt-acct-head">
        <span class="nt-avatar" aria-hidden="true">
            @if ($avatar)<img src="{{ $avatar }}" alt="">@else{{ $user->initials() ?: '♠' }}@endif
        </span>
        <div class="min-w-0">
            <h1>{{ $user->name }}</h1>
            <div class="nt-acct-sub">
                <span>Member since {{ $user->created_at->format('M Y') }}</span>
                @if ($user->hasVerifiedPhone())
                    <x-ui.pill tone="ok">Phone confirmed</x-ui.pill>
                @else
                    <button type="button" x-on:click="$dispatch('open-phone-required', { purpose: 'verify' })"><x-ui.pill tone="warn">Confirm your phone</x-ui.pill></button>
                @endif
            </div>
        </div>
        @if ($balance !== null)
            <div class="nt-acct-bal"><span>Balance</span><b class="nt-num">KES {{ \App\Support\WalletAmount::format($balance) }}</b></div>
        @endif
    </header>

    <nav class="nt-tabs nt-subnav" aria-label="Account">
        @foreach ($nav->accountArea() as $item)
            <a href="{{ $item['href'] }}" wire:navigate class="nt-tab" @if (($item['route'] ?? null) === $active) aria-current="page" @endif>{{ $item['label'] }}</a>
        @endforeach
    </nav>

    {{ $slot }}
</div>
