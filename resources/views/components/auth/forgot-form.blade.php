{{-- Forgot password: Fortify e-mails a reset link (password.email). --}}
@props(['sheet' => false])
<div class="grid gap-4">
    <p class="nt-hint m-0">Enter the e-mail you signed up with and we'll send you a link to choose a new password.</p>
    @if (session('status') && session('signin_form') === 'forgot')<p class="nt-ok" role="status">{{ session('status') }}</p>@endif

    <form method="POST" action="{{ route('password.email') }}" class="grid gap-4">
        @csrf
        <input type="hidden" name="_sheet" value="forgot">
        <x-auth.field form="forgot" name="email" label="E-mail" type="email" :value="old('email')" required autocomplete="email" inputmode="email" />
        <x-ui.button type="submit" size="lg" block data-test="email-password-reset-link-button">Send the reset link</x-ui.button>
    </form>

    <p class="m-0 text-center text-sm text-soft">
        @if ($sheet)
            <button type="button" class="font-semibold text-gold hover:underline" x-on:click="tab = 'login'">Back to log in</button>
        @else
            <a href="{{ route('login') }}" class="font-semibold text-gold hover:underline">Back to log in</a>
        @endif
    </p>
</div>
