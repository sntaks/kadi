<x-layouts::auth :title="__('Finish signing up')">
    <div class="grid gap-6">
        <x-auth.head title="One last step" :description="__('Welcome, :name. Confirm the details below to create your account.', ['name' => $name])" />
        <form method="POST" action="{{ route('auth.google.complete.store') }}" class="grid gap-5">
            @csrf
            <x-referral-code-field />
            @if (config('kadi.promotions.enabled'))
                <livewire:auth.promo-code-field :error="$errors->first('promo_code')" />
            @endif
            <x-consent-fields />
            <x-ui.button type="submit" size="lg" block data-test="google-complete-button">Create my account</x-ui.button>
        </form>
        <p class="m-0 text-center"><a href="{{ route('auth.google.complete.cancel') }}" class="text-sm text-soft hover:text-gold">Cancel</a></p>
    </div>
</x-layouts::auth>
