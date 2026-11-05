<x-auth.page :title="__('Two-factor authentication')">
    <div class="grid gap-6"
         x-data="{
            recovery: @js($errors->has('recovery_code')),
            toggle() {
                this.recovery = ! this.recovery;
                $nextTick(() => (this.recovery ? $refs.recovery : $refs.code)?.focus());
            },
         }">
        <x-auth.head title="Two-factor sign-in">
            <p class="m-0 text-[15px] text-soft" x-show="! recovery">Enter the 6-digit code from your authenticator app.</p>
            <p class="m-0 text-[15px] text-soft" x-show="recovery" x-cloak>Enter one of the recovery codes you saved when you turned on two-factor sign-in.</p>
        </x-auth.head>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="grid gap-4">
            @csrf
            <div x-show="! recovery">
                <x-auth.field name="code" label="Authentication code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*" x-ref="code" x-bind:disabled="recovery" autofocus />
            </div>
            <div x-show="recovery" x-cloak>
                <x-auth.field name="recovery_code" label="Recovery code" autocomplete="one-time-code" x-ref="recovery" x-bind:disabled="! recovery" />
            </div>
            <x-ui.button type="submit" size="lg" block>Continue</x-ui.button>
        </form>

        <button type="button" class="text-center text-sm font-semibold text-gold hover:underline" x-on:click="toggle()">
            <span x-show="! recovery">Use a recovery code instead</span>
            <span x-show="recovery" x-cloak>Use an authentication code instead</span>
        </button>
    </div>
</x-auth.page>
