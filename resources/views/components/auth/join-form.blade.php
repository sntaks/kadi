{{--
    Join: Google, or the e-mail form posted to Fortify (register.store). Keeps every sign-up rule of the
    old page: the player-name rules, a Kenyan phone, the optional referral and promo codes (they are saved
    for ProcessVerifiedUser) and the 18+ and terms consent.
--}}
@props(['returnTo' => null, 'sheet' => false])
<div class="grid gap-4">
    <x-auth.google-button label="Join with Google" :return-to="$returnTo" />
    <p class="nt-hint -mt-2 text-center">{{ __("You'll confirm you're :age+ and accept our terms on the next step.", ['age' => config('kadi.min_age')]) }}</p>
    <x-auth.or />

    <form method="POST" action="{{ route('register.store') }}" class="grid gap-4">
        @csrf
        <input type="hidden" name="_sheet" value="join">
        @if ($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif

        <x-player-name-field :value="old('name')" :placeholder="__('Player name')" />
        <x-auth.field form="join" name="email" label="E-mail" type="email" :value="old('email')" required autocomplete="email" inputmode="email" />
        <x-auth.field form="join" name="phone" label="M-Pesa number" type="tel" :value="old('phone')" required autocomplete="tel" placeholder="07XX XXX XXX"
                      hint="Deposits and withdrawals go to this number." />
        <x-auth.field form="join" name="password" label="Password" type="password" required autocomplete="new-password" />
        <x-auth.field form="join" name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />

        {{-- In the sheet (on every guest page) neither field asks KadiApi until the Join tab is shown. --}}
        <x-referral-code-field :lookup="! $sheet" />
        @if (config('kadi.promotions.enabled'))
            @if ($sheet)
                <livewire:auth.promo-code-field :error="$errors->first('promo_code')" :initial="old('promo_code')" lazy />
            @else
                <livewire:auth.promo-code-field :error="$errors->first('promo_code')" />
            @endif
        @endif
        <x-consent-fields />

        <x-ui.button type="submit" size="lg" block data-test="register-user-button">Join Kadi</x-ui.button>
    </form>

    <p class="m-0 text-center text-sm text-soft">Already have an account?
        @if ($sheet)
            <button type="button" class="font-semibold text-gold hover:underline" x-on:click="tab = 'login'">Log in</button>
        @else
            <a href="{{ route('login') }}" class="font-semibold text-gold hover:underline">Log in</a>
        @endif
    </p>
</div>
