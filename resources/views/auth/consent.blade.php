<x-auth.page :title="__('Confirm to continue')">
    <div class="grid gap-6">
        <x-auth.head title="Before you continue" description="Confirm your age and accept our terms to keep playing on Kadi." />
        <form method="POST" action="{{ route('consent.store') }}" class="grid gap-5">
            @csrf
            <x-consent-fields />
            <x-ui.button type="submit" size="lg" block data-test="consent-button">Continue</x-ui.button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-sm text-soft hover:text-gold">Log out</button>
        </form>
    </div>
</x-auth.page>
