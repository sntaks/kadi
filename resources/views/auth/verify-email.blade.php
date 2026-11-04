<x-layouts::auth :title="__('Verify your e-mail')">
    <div class="grid gap-6">
        <x-auth.head title="Check your e-mail" description="We sent you a link. Open it to verify your e-mail, then you can play, deposit and withdraw." />

        @if (auth()->user()?->awaitsSignupBonus())
            <div class="nt-callout" data-test="promo-verify-nudge">
                <span>{{ __('Verify your email and phone now to get your KES :amount signup bonus. Promo codes expire, so don\'t wait.', ['amount' => config('kadi.promotions.signup_bonus_amount')]) }}</span>
            </div>
        @endif

        @if (session('status') == 'verification-link-sent')
            <p class="nt-ok" role="status">We've sent a new link to your e-mail.</p>
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button type="submit" size="lg" block>Send a new link</x-ui.button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-sm text-soft hover:text-gold">Log out</button>
        </form>
    </div>
</x-layouts::auth>
