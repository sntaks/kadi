{{--
    The one notice strip under the top bar, on every page until the player acts: an unverified e-mail
    first (nothing else works until it is), then an unconfirmed phone (needed for deposits, withdrawals
    and referral payouts). The only place these reminders appear: no page adds its own banner for them.
--}}
@php($user = auth()->user())
@if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
    @unless (request()->routeIs('verification.notice'))
        <x-ui.notice data-test="notice-email">
            @if (session('status') === 'verification-link-sent')
                <b>We've sent a new link.</b> Open it from your inbox to verify your e-mail.
            @else
                <b>Verify your e-mail</b> to play, deposit and withdraw. The link is in your inbox.
            @endif
            <x-slot:action>
                <form method="POST" action="{{ route('verification.send') }}" class="ml-auto max-[640px]:ml-0">
                    @csrf
                    <x-ui.button variant="ghost" size="sm" type="submit">Send a new link</x-ui.button>
                </form>
            </x-slot:action>
        </x-ui.notice>
    @endunless
@elseif ($user && ! $user->hasVerifiedPhone())
    <div x-data="{ done: false }" x-show="! done" x-on:phone-verified.window="done = true">
        <x-ui.notice data-test="notice-phone">
            @if ($user->awaitsSignupBonus())
                <b>Confirm your M-Pesa number</b> to get your KES {{ config('kadi.promotions.signup_bonus_amount') }} signup bonus. Promo codes expire, so don't wait.
            @elseif (empty($user->phone))
                <b>Add your M-Pesa number</b> to deposit and withdraw.
            @else
                <b>Confirm your M-Pesa number</b> with a quick SMS code to deposit and withdraw.
            @endif
            <x-slot:action>
                <x-ui.button size="sm" x-on:click="$dispatch('open-phone-required', { purpose: 'verify' })">Confirm number</x-ui.button>
            </x-slot:action>
        </x-ui.notice>
    </div>
@endif
