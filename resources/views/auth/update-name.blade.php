<x-auth.page :title="__('Choose a new name')">
    <div class="grid gap-6">
        <x-auth.head title="Choose a new player name" description="Your current name no longer meets our naming rules. Pick a new one to keep playing." />
        <form method="POST" action="{{ route('name.update') }}" class="grid gap-4">
            @csrf
            <x-player-name-field :value="old('name', $suggestion)" autofocus />
            @if ($suggestion !== '' && ! old('name'))
                <p class="nt-hint -mt-2">We suggested a name based on your current one. Change it if you like.</p>
            @endif
            <x-ui.button type="submit" size="lg" block data-test="update-name-button">Save name</x-ui.button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-sm text-soft hover:text-gold">Log out</button>
        </form>
    </div>
</x-auth.page>
