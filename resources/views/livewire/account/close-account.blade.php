<section aria-labelledby="close-account-title">
    <h3 id="close-account-title" class="mb-1 text-lg font-bold text-ivory">Close your account</h3>
    <p class="nt-hint mb-4">Closing your account deletes your profile here. You can only close it once your wallet and Invite &amp; Earn balances are empty and nothing is in progress. Any break or limit you set stays in place if you sign up again.</p>

    @if (! $confirming)
        <x-ui.button variant="ghost" wire:click="start" data-test="close-account-start">Close my account</x-ui.button>
    @else
        <form wire:submit="close" class="grid gap-4">
            @if ($needsPassword)
                <label class="nt-field">
                    <span>Your password</span>
                    <input type="password" wire:model="password" autocomplete="current-password" required>
                    @error('password')<span class="nt-error">{{ $message }}</span>@enderror
                </label>
            @endif
            <label class="nt-field">
                <span>Type {{ \App\Livewire\Account\CloseAccount::CONFIRM_WORD }} to confirm</span>
                <input type="text" wire:model="confirmWord" autocomplete="off" autocapitalize="characters" required>
                @error('confirmWord')<span class="nt-error">{{ $message }}</span>@enderror
            </label>

            @if ($blockers !== [])
                <div class="nt-callout" role="alert" data-test="close-account-blocked">
                    <span><b>Your account can't be closed yet.</b>
                        @foreach ($blockers as $blocker)<br>{{ $blocker }}@endforeach
                    </span>
                </div>
            @endif
            @if ($error)<p class="nt-error" role="alert">{{ $error }}</p>@endif

            <div class="flex flex-wrap gap-2">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="close" data-test="close-account-confirm">Close my account for good</x-ui.button>
                <x-ui.button variant="quiet" wire:click="cancel">Keep my account</x-ui.button>
            </div>
        </form>
    @endif
</section>
