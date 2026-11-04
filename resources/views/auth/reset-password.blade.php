<x-layouts::auth :title="__('Reset password')">
    <div class="grid gap-6">
        <x-auth.head title="Choose a new password" />
        @if (session('status'))<p class="nt-ok" role="status">{{ session('status') }}</p>@endif
        <form method="POST" action="{{ route('password.update') }}" class="grid gap-4">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">
            <x-auth.field name="email" label="E-mail" type="email" :value="old('email', request('email'))" required autocomplete="email" />
            <x-auth.field name="password" label="New password" type="password" required autocomplete="new-password" />
            <x-auth.field name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
            <x-ui.button type="submit" size="lg" block data-test="reset-password-button">Save my new password</x-ui.button>
        </form>
    </div>
</x-layouts::auth>
