@assets
    @vite('resources/js/passkeys.js')
@endassets

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="flex items-center gap-2 text-lg font-bold text-ivory">
                <flux:icon.finger-print variant="outline" class="size-5 text-gold" />
                {{ __('Passkeys') }}
            </h3>
            <p class="mt-1 text-sm text-ash">
                {{ __('Sign in with your face, fingerprint, or device PIN instead of a password.') }}
            </p>
        </div>

        @if ($accessConfirmed)
            <x-passkey-registration />
        @endif
    </div>

    @if (! $accessConfirmed)
        <x-security.password-gate />
    @else
    @if (filled($statusMessage))
        <p
            class="rounded-lg border border-green-700/50 bg-green-900/30 px-4 py-2 text-sm text-win"
            role="status"
        >
            {{ $statusMessage }}
        </p>
    @endif

    @if (empty($passkeys))
        <div class="rounded-lg border border-line bg-felt p-6 text-center">
            <flux:icon.key variant="outline" class="mx-auto size-8 text-ash" />
            <p class="mt-3 text-sm font-semibold text-ivory">
                {{ __('No passkeys yet') }}
            </p>
            <p class="mt-1 text-xs text-ash">
                {{ __('Add a passkey for a faster, more secure way to sign in.') }}
            </p>
        </div>
    @else
        <ul class="divide-y divide-line rounded-lg border border-line bg-felt" aria-label="{{ __('Your passkeys') }}">
            @foreach ($passkeys as $passkey)
                <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between" wire:key="passkey-{{ $passkey['id'] }}">
                    <div class="flex min-w-0 items-start gap-3">
                        <flux:icon.device-phone-mobile variant="outline" class="mt-0.5 size-5 shrink-0 text-gold/70" />

                        <div class="min-w-0">
                            @if ($editingId === $passkey['id'])
                                <form wire:submit="renamePasskey" class="flex items-center gap-2" wire:key="rename-{{ $passkey['id'] }}">
                                    <flux:input
                                        wire:model="name"
                                        :aria-label="__('Passkey name')"
                                        class="max-w-xs"
                                        autofocus
                                    />
                                    <flux:button
                                        variant="primary"
                                        type="submit"
                                        size="sm"
                                        wire:loading.attr="disabled"
                                        wire:target="renamePasskey"
                                    >
                                        {{ __('Save') }}
                                    </flux:button>
                                    <flux:button variant="ghost" size="sm" wire:click="cancelRenaming">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                </form>
                                @error('name')
                                    <p class="mt-1 text-xs text-hot">{{ $message }}</p>
                                @enderror
                            @else
                                <p class="truncate text-sm font-semibold text-ivory">{{ $passkey['name'] }}</p>
                                <p class="mt-0.5 text-xs text-ash">
                                    @if ($passkey['authenticator'])
                                        {{ $passkey['authenticator'] }} ·
                                    @endif
                                    {{ __('Added :date', ['date' => $passkey['added']]) }} ·
                                    {{ __('Last used :when', ['when' => $passkey['last_used']]) }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2 self-end sm:self-center">
                        @if ($editingId !== $passkey['id'])
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="pencil"
                                wire:click="startRenaming({{ $passkey['id'] }})"
                            >
                                {{ __('Rename') }}
                            </flux:button>
                            <flux:button
                                variant="danger"
                                size="sm"
                                icon="trash"
                                wire:click="requestRemoval({{ $passkey['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="removePasskey"
                            >
                                {{ __('Remove') }}
                            </flux:button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
    @endif

    <flux:modal
        name="confirm-passkey-removal"
        wire:model="confirmingRemoval"
        class="max-w-md"
    >
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Remove passkey?') }}</flux:heading>

                <flux:subheading>
                    {{ __('You will no longer be able to sign in with this passkey. This cannot be undone.') }}
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" wire:click="cancelRemoval">
                        {{ __('Cancel') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    variant="danger"
                    wire:click="removePasskey"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading wire:target="removePasskey" class="animate-spin mr-1">⟳</span>
                    {{ __('Remove passkey') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
