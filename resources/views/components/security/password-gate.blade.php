<div class="rounded-lg border border-yellow-800/20 bg-[#111111] p-6">
    <div class="flex items-start gap-3">
        <flux:icon.lock-closed variant="outline" class="mt-0.5 size-5 shrink-0 text-[#f5c542]" />

        <div class="w-full">
            <h4 class="text-sm font-bold text-[#f5f5f0]" @unless (config('platform.ui_v2')) style="font-family: 'Cinzel', serif;" @endunless>
                {{ __('Confirm your password') }}
            </h4>
            <p class="mt-1 text-xs text-[#6b6b6b]">
                {{ __('This is a sensitive area. Please confirm your password to continue.') }}
            </p>

            <form wire:submit="confirmAccess" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                <flux:input
                    wire:model="confirmPassword"
                    :label="__('Password')"
                    type="password"
                    class="flex-1"
                    autocomplete="current-password"
                />
                <flux:button
                    variant="primary"
                    type="submit"
                    class="sm:mt-7"
                    wire:loading.attr="disabled"
                    wire:target="confirmAccess"
                >
                    <span wire:loading wire:target="confirmAccess" class="animate-spin mr-1">⟳</span>
                    {{ __('Confirm') }}
                </flux:button>
            </form>
            @error('confirmPassword')
                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
