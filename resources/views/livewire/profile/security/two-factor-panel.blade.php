<div class="space-y-4" wire:cloak>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="flex items-center gap-2 text-lg font-bold text-ivory">
                <flux:icon.shield-check variant="outline" class="size-5 text-gold" />
                {{ __('Two-Factor Authentication') }}
            </h3>
            <p class="mt-1 text-sm text-ash">
                {{ __('Add an extra layer of security to your account with an authenticator app.') }}
            </p>
        </div>

        @if ($accessConfirmed)
            <span class="inline-flex items-center gap-2 self-start rounded-full border px-3 py-1 text-xs font-semibold
                {{ $twoFactorEnabled
                    ? 'border-green-700 bg-green-900/40 text-win'
                    : 'border-red-700/50 bg-red-900/20 text-hot' }}">
                {{ $twoFactorEnabled ? '✓ '.__('Enabled') : '! '.__('Disabled') }}
            </span>
        @endif
    </div>

    @if (! $accessConfirmed)
        <x-security.password-gate />
    @elseif (filled($statusMessage))
        <p class="rounded-lg border border-green-700/50 bg-green-900/30 px-4 py-2 text-sm text-win" role="status">
            {{ $statusMessage }}
        </p>
    @endif

    @if ($canManageTwoFactor && $accessConfirmed)
        @if ($twoFactorEnabled)
            <div class="space-y-4 rounded-lg border border-line bg-felt p-5">
                <p class="text-sm text-ash">
                    {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                </p>

                <livewire:pages::settings.two-factor.recovery-codes />

                <flux:button
                    variant="danger"
                    icon="shield-exclamation"
                    wire:click="$set('confirmingDisable', true)"
                >
                    {{ __('Disable 2FA') }}
                </flux:button>

                <flux:modal name="confirm-two-factor-disable" wire:model="confirmingDisable" class="max-w-md">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Disable two-factor authentication?') }}</flux:heading>
                            <flux:subheading>
                                {{ __('Your account will be protected by password only. You can enable 2FA again at any time.') }}
                            </flux:subheading>
                        </div>

                        <div class="flex justify-end gap-2">
                            <flux:modal.close>
                                <flux:button variant="filled" wire:click="$set('confirmingDisable', false)">
                                    {{ __('Cancel') }}
                                </flux:button>
                            </flux:modal.close>

                            <flux:button variant="danger" wire:click="disableTwoFactor" wire:loading.attr="disabled">
                                <span wire:loading wire:target="disableTwoFactor" class="animate-spin mr-1">⟳</span>
                                {{ __('Disable 2FA') }}
                            </flux:button>
                        </div>
                    </div>
                </flux:modal>
            </div>
        @else
            <div class="space-y-4 rounded-lg border border-line bg-felt p-5">
                <p class="text-sm text-ash">
                    {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                </p>

                <flux:modal.trigger name="two-factor-setup-modal">
                    <flux:button
                        variant="primary"
                        wire:click="$dispatch('start-two-factor-setup')"
                    >
                        {{ __('Enable 2FA') }}
                    </flux:button>
                </flux:modal.trigger>

                <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
            </div>
        @endif
    @endif
</div>
