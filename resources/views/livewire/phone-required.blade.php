<div>
@if ($show)
<div
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="background: rgba(0,0,0,0.88); backdrop-filter: blur(10px);"
    x-data
    x-on:keydown.escape.window="$wire.close()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="phone-required-title"
>
    <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-gold/25 bg-felt shadow-[0_0_80px_rgba(245,197,66,0.12)]">

        {{-- Top shimmer line --}}
        <div class="h-px w-full" style="background: linear-gradient(90deg, transparent, var(--color-gold), transparent);"></div>

        {{-- Radial glow (purely decorative) --}}
        <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-48 -translate-x-1/2 rounded-full"
             style="background: radial-gradient(circle, rgba(245,197,66,0.1) 0%, transparent 70%);"></div>

        <button type="button" wire:click="close" class="absolute right-4 top-4 z-10 rounded-full p-1 text-ash transition hover:text-white" aria-label="{{ __('Close') }}">
            <flux:icon.x-mark variant="mini" />
        </button>

        <div class="relative px-8 pb-8 pt-7">

            {{-- Avatar + badge --}}
            <div class="mb-6 flex justify-center">
                <div class="relative">
                    @php
                        $avatarSrc = auth()->user()->avatar
                            ?? ('https://www.gravatar.com/avatar/' . md5(strtolower(trim(auth()->user()->email))) . '?d=robohash&r=pg&s=80');
                    @endphp
                    <img src="{{ $avatarSrc }}" alt="{{ auth()->user()->name }}"
                         class="h-20 w-20 rounded-full object-cover ring-2 ring-gold/40" />
                    <span class="absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-full bg-gold text-black shadow-lg">
                        <flux:icon.device-phone-mobile variant="micro" />
                    </span>
                </div>
            </div>

            {{-- Heading --}}
            <div class="mb-6 text-center">
                <h2 id="phone-required-title" class="mb-1 text-2xl font-black text-white">
                    {{ $step === 'code' ? __('Confirm your number') : __('One Last Step') }}
                </h2>
                <p class="text-sm text-ash">
                    Hey <span class="font-semibold text-gold">{{ auth()->user()->name }}</span>,
                    @switch($purpose)
                        @case('deposit') {{ __('confirm your M-Pesa number to deposit funds.') }} @break
                        @case('withdraw') {{ __('confirm your M-Pesa number to withdraw.') }} @break
                        @case('referral') {{ __('confirm your M-Pesa number to withdraw your referral earnings.') }} @break
                        @default {{ __('confirm your M-Pesa number to keep your account secure.') }}
                    @endswitch
                </p>
            </div>

            @if ($step === 'phone')
                {{-- Why it matters --}}
                <div class="mb-6 rounded-xl border border-gold/10 bg-gold/5 px-4 py-3">
                    <ul class="space-y-1.5 text-xs text-ash">
                        <li class="flex items-center gap-2"><flux:icon.check variant="micro" class="text-gold" /> {{ __('Deposits are charged to this M-Pesa number') }}</li>
                        <li class="flex items-center gap-2"><flux:icon.check variant="micro" class="text-gold" /> {{ __('Withdrawals are paid to it') }}</li>
                        <li class="flex items-center gap-2"><flux:icon.check variant="micro" class="text-gold" /> {{ __('We text you a code to confirm it') }}</li>
                    </ul>
                </div>

                {{-- Phone input --}}
                <div class="mb-5">
                    <label for="phone-required-phone" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-ash">
                        {{ __('Phone Number') }}
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-ash">
                            <flux:icon.phone variant="micro" />
                        </span>
                        <input
                            id="phone-required-phone"
                            wire:model="phone"
                            type="tel"
                            inputmode="tel"
                            autocomplete="tel"
                            placeholder="+254 7XX XXX XXX"
                            autofocus
                            class="w-full rounded-xl border bg-felt-2 py-3 pl-10 pr-4 text-sm text-white placeholder-ash/60 transition focus:outline-none focus:ring-0
                                   {{ $errors->has('phone') ? 'border-hot/60 focus:border-hot' : 'border-line focus:border-gold/60' }}"
                            wire:keydown.enter="save"
                        />
                    </div>
                    @error('phone')
                        <p class="mt-1.5 text-xs text-hot">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    wire:click="save"
                    wire:loading.attr="disabled"
                    class="nt-btn nt-btn-gold disabled:cursor-not-allowed disabled:opacity-50 nt-btn-block"
                >
                    <span wire:loading.remove wire:target="save">{{ __('Send me a code') }}</span>
                    <span wire:loading wire:target="save">{{ __('Sending…') }}</span>
                </button>
            @else
                <div
                    x-data="{ left: @js($resendIn), timer: null }"
                    x-init="timer = setInterval(() => { if (left > 0) left-- }, 1000)"
                    x-effect="left = $wire.resendIn"
                    x-on:remove="clearInterval(timer)"
                >
                    @php $hasCode = app(\App\Support\PhoneOtp::class)->hasPendingCode(auth()->user()); @endphp
                    <p class="mb-4 text-center text-sm text-ash">
                        @if ($notice)
                            {{ $notice }}
                        @elseif ($hasCode)
                            {{ __('Enter the code we sent to :phone.', ['phone' => \App\Services\TextSmsService::mask(auth()->user()->phone)]) }}
                        @else
                            {{ __('We will text a code to :phone.', ['phone' => \App\Services\TextSmsService::mask(auth()->user()->phone)]) }}
                        @endif
                    </p>

                    @if ($hasCode)
                        <div class="mb-5">
                            <label for="phone-required-code" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-ash">
                                {{ __('Code') }}
                            </label>
                            <input
                                id="phone-required-code"
                                wire:model="code"
                                type="text"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="{{ config('kadi.phone_otp.length') }}"
                                placeholder="{{ str_repeat('•', config('kadi.phone_otp.length')) }}"
                                autofocus
                                class="w-full rounded-xl border bg-felt-2 py-3 text-center text-lg tracking-[0.5em] text-white placeholder-ash/60 transition focus:outline-none focus:ring-0
                                       {{ $errors->has('code') ? 'border-hot/60 focus:border-hot' : 'border-line focus:border-gold/60' }}"
                                wire:keydown.enter="verify"
                                data-test="otp-input"
                            />
                            @error('code')
                                <p class="mt-1.5 text-xs text-hot">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            wire:click="verify"
                            wire:loading.attr="disabled"
                            class="nt-btn nt-btn-gold disabled:cursor-not-allowed disabled:opacity-50 nt-btn-block"
                        >
                            <span wire:loading.remove wire:target="verify">{{ __('Confirm') }}</span>
                            <span wire:loading wire:target="verify">{{ __('Checking…') }}</span>
                        </button>
                    @else
                        @error('code')
                            <p class="mb-4 text-center text-xs text-hot">{{ $message }}</p>
                        @enderror

                        <button
                            type="button"
                            wire:click="sendCode"
                            wire:loading.attr="disabled"
                            x-bind:disabled="left > 0"
                            class="nt-btn nt-btn-gold disabled:cursor-not-allowed disabled:opacity-50 nt-btn-block"
                            data-test="otp-send"
                        >
                            <span wire:loading.remove wire:target="sendCode">{{ __('Send me a code') }}</span>
                            <span wire:loading wire:target="sendCode">{{ __('Sending…') }}</span>
                        </button>
                    @endif

                    <div class="mt-4 flex items-center justify-between text-xs">
                        <button type="button" wire:click="changeNumber" class="text-ash hover:text-white">{{ __('Wrong number?') }}</button>

                        @if ($hasCode)
                            <button type="button" wire:click="sendCode" wire:loading.attr="disabled" x-bind:disabled="left > 0"
                                    class="font-semibold text-gold hover:underline disabled:cursor-not-allowed disabled:text-ash disabled:no-underline">
                                <span x-show="left > 0">{{ __('Resend in') }} <span x-text="left"></span>s</span>
                                <span x-show="left <= 0">{{ __('Resend code') }}</span>
                            </button>
                        @else
                            <span x-show="left > 0" class="text-ash">{{ __('New code in') }} <span x-text="left"></span>s</span>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
@endif
</div>
