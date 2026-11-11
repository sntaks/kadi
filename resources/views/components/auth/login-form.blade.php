{{--
    Log in: Google, then e-mail and password, posted to Fortify (login.store: its throttling and CSRF).
    Used by the sign-in sheet and the /login page. `returnTo` is the page to land on afterwards.
    `sheet` renders "Forgot" and "Join" as sheet tabs; on the page they are links.
--}}
@props(['returnTo' => null, 'sheet' => false])
<div class="grid gap-4">
    @if (session('status') && in_array(session('signin_form'), [null, 'login'], true))<p class="nt-ok" role="status">{{ session('status') }}</p>@endif

    {{-- Refused by the one-device rule (App\Auth\SingleSession): offer the e-mailed link that signs the other device out. --}}
    @if (session()->has(\App\Auth\SingleSession::PENDING_KEY))
        <form method="POST" action="{{ route('login.release-device') }}" class="grid gap-2 rounded-panel border border-line p-3" data-test="release-device">
            @csrf
            <p class="m-0 text-sm text-soft">Lost that device, or can't reach it? We'll e-mail you a link that signs it out.</p>
            <x-ui.button type="submit" variant="ghost" block>E-mail me a sign-out link</x-ui.button>
        </form>
    @endif

    <x-auth.google-button :return-to="$returnTo" />
    <x-auth.or />

    <form method="POST" action="{{ route('login.store') }}" class="grid gap-4">
        @csrf
        <input type="hidden" name="_sheet" value="login">
        @if ($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif

        <x-auth.field form="login" name="email" label="E-mail" type="email" :value="old('email')" required autocomplete="email" inputmode="email" />
        <x-auth.field form="login" name="password" label="Password" type="password" required autocomplete="current-password" />

        <div class="flex items-center justify-between gap-3 text-sm">
            <label class="flex items-center gap-2 text-soft"><input type="checkbox" name="remember" value="1" class="size-4 accent-gold" @checked(old('remember'))> Keep me logged in</label>
            @if ($sheet)
                <button type="button" class="font-semibold text-gold hover:underline" x-on:click="tab = 'forgot'">Forgot password?</button>
            @else
                <a href="{{ route('password.request') }}" class="font-semibold text-gold hover:underline">Forgot password?</a>
            @endif
        </div>

        <x-ui.button type="submit" size="lg" block data-test="login-button">Log in</x-ui.button>
    </form>

    <p class="m-0 text-center text-sm text-soft">New to Kadi?
        @if ($sheet)
            <button type="button" class="font-semibold text-gold hover:underline" x-on:click="tab = 'join'">Join now</button>
        @else
            <a href="{{ route('register') }}" class="font-semibold text-gold hover:underline">Join now</a>
        @endif
    </p>
</div>
