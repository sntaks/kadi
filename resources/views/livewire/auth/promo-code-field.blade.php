{{--
    Optional signup-bonus promo code. An invalid code never blocks sign-up; KadiApi decides at
    POST customers (after the e-mail is verified) whether it applies.
--}}
<div x-data="{ open: @js(filled($code) || filled($error)) }" class="flex flex-col gap-2" data-test="promo-code-field">
    <button type="button" x-show="! open" x-on:click="open = true; $nextTick(() => $refs.promo.focus())"
            class="self-start text-sm text-gold hover:underline">
        {{ __('Have a promo code?') }}
    </button>

    <div x-show="open" x-cloak>
        <flux:input
            x-ref="promo"
            wire:model.live.debounce.800ms="code"
            name="promo_code"
            :value="$code"
            :label="__('Promo code (optional)')"
            maxlength="30"
            autocomplete="off"
            autocapitalize="characters"
            spellcheck="false"
            placeholder="KADI20"
        />

        @if ($error)
            <p class="mt-1.5 text-sm font-medium text-hot" role="alert">{{ $error }}</p>
        @elseif ($status === 'valid')
            <div class="mt-2 rounded-lg border border-green-700/40 bg-green-900/20 px-3 py-2 text-sm text-win" role="status" data-test="promo-code-valid">
                <p class="font-semibold">{{ __('Promo code applied') }}</p>
                <p class="mt-0.5 text-xs text-green-300/80">
                    @if ($expiresAt)
                        {{ __('Verify your email and phone before :date to get your KES :amount signup bonus.', ['date' => $expiresAt, 'amount' => $bonus]) }}
                    @else
                        {{ __('Verify your email and phone straight away to get your KES :amount signup bonus.', ['amount' => $bonus]) }}
                    @endif
                </p>
            </div>
        @elseif ($status === 'invalid')
            <p class="mt-1.5 text-xs text-ash" role="status" data-test="promo-code-invalid">
                {{ __("This promo code isn't valid. You can still sign up without it.") }}
            </p>
        @endif
    </div>
</div>
