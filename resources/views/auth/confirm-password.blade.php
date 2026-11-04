<x-layouts::auth :title="__('Confirm password')">
    <div class="grid gap-6">
        <x-auth.head title="Confirm your password" description="This part of your account needs your password before you continue." />
        @if (session('status'))<p class="nt-ok" role="status">{{ session('status') }}</p>@endif
        <form method="POST" action="{{ route('password.confirm.store') }}" class="grid gap-4">
            @csrf
            <x-auth.field name="password" label="Password" type="password" required autocomplete="current-password" />
            <x-ui.button type="submit" size="lg" block data-test="confirm-password-button">Continue</x-ui.button>
        </form>
    </div>
</x-layouts::auth>
