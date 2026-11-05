{{--
    Optional referral or agent code on sign-up (email form and the Google consent page).
    Pre-filled from a referral link (?ref=, kept by CaptureReferralCode). KadiApi decides whether the
    code is a player's referral code or an agent code; an unknown code never blocks sign-up.
--}}
@props(['lookup' => true])
@if (config('kadi.referrals.enabled'))
    @php
        $pending = \App\Referrals\ReferralCode::pending(request());
        $value = old('referral_code', $pending);
        // `lookup` false (the sign-in sheet, on every guest page) never asks KadiApi who invited them.
        $inviter = $pending !== null && $lookup ? \App\Referrals\ReferralCode::inviterFirstName($pending) : null;
    @endphp

    <div x-data="{ open: @js(filled($value) || $errors->has('referral_code')) }" class="flex flex-col gap-2" data-test="referral-code-field">
        @if ($inviter)
            <div @class(['flex items-center gap-2 px-3 py-2 text-sm', 'nt-callout'])>
                <flux:icon.user-plus variant="mini" />
                <span>{{ __('Invited by :name', ['name' => $inviter]) }}</span>
            </div>
        @endif

        <button type="button" x-show="! open" x-on:click="open = true; $nextTick(() => $refs.code.focus())"
                @class(['self-start text-sm hover:underline', 'font-semibold text-gold'])>
            {{ __('Have a referral or agent code?') }}
        </button>

        <div x-show="open" x-cloak>
            <flux:input
                x-ref="code"
                name="referral_code"
                :label="__('Referral or agent code (optional)')"
                :value="$value"
                maxlength="32"
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                placeholder="KADI2026"
            />
            @if ($pending !== null && $inviter === null && $lookup)
                <p class="mt-1.5 text-xs text-ash">
                    {{ __("We couldn't find a player with this code. If it's an agent code, that's fine.") }}
                </p>
            @endif
        </div>
    </div>
@endif
