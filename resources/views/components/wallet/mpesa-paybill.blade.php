{{--
    How to load the wallet by hand with M-Pesa Pay Bill. KadiApi credits a paybill payment to the customer
    whose account number matches the bill ref, so the account number shown here must be the player's own.
    The paybill is config('kadi.paybill') (MPESA_SHORTCODE).
--}}
@props(['paybill', 'accountNo'])

<section
    {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-[#39b54a]/40 shadow-[0_0_24px_rgba(57,181,74,0.12)]']) }}
    aria-labelledby="mpesa-paybill-title"
    data-test="mpesa-paybill-card"
    x-data="{
        copied: null,
        copy(text, what) {
            navigator.clipboard?.writeText(text).then(() => { this.copied = what; setTimeout(() => this.copied = null, 2000) });
        },
    }"
>
    {{-- Header --}}
    <div class="flex items-center gap-3 px-5 py-4" style="background: linear-gradient(135deg, #0b6b2e 0%, #1f9a3d 55%, #39b54a 100%);">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/30 bg-white text-center leading-none">
            <span class="text-[11px] font-black tracking-tight text-[#1f9a3d]">M<span class="text-[#e4002b]">-</span>PESA</span>
        </div>
        <div class="min-w-0">
            <h3 id="mpesa-paybill-title" class="text-sm font-black uppercase tracking-wide text-white">Load with M-Pesa Pay Bill</h3>
            <p class="text-xs text-white/80">Pay from your M-Pesa menu or the M-PESA app</p>
        </div>
    </div>

    <div class="space-y-4 bg-[#0c1f12] px-5 py-4">
        {{-- Business number --}}
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="text-[10px] font-semibold uppercase tracking-widest text-[#8fd19a]">Business No.</div>
                <div class="font-mono text-xl font-black tracking-widest text-white" data-test="mpesa-paybill-number">{{ $paybill }}</div>
            </div>
            <button type="button" x-on:click="copy(@js((string) $paybill), 'paybill')"
                    class="shrink-0 rounded-full border border-[#39b54a]/60 px-3 py-1 text-xs font-semibold text-[#8fd19a] transition hover:bg-[#39b54a] hover:text-white"
                    :class="copied === 'paybill' && 'bg-[#39b54a] text-white'"
                    x-text="copied === 'paybill' ? 'Copied' : 'Copy'"
                    aria-label="{{ __('Copy business number') }}">Copy</button>
        </div>

        <div class="h-px bg-[#39b54a]/20"></div>

        {{-- Account number --}}
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="text-[10px] font-semibold uppercase tracking-widest text-[#8fd19a]">Account No.</div>
                <div class="break-all font-mono text-xl font-black tracking-widest text-white" data-test="mpesa-paybill-account">{{ $accountNo }}</div>
            </div>
            <button type="button" x-on:click="copy(@js((string) $accountNo), 'account')"
                    class="shrink-0 rounded-full border border-[#39b54a]/60 px-3 py-1 text-xs font-semibold text-[#8fd19a] transition hover:bg-[#39b54a] hover:text-white"
                    :class="copied === 'account' && 'bg-[#39b54a] text-white'"
                    x-text="copied === 'account' ? 'Copied' : 'Copy'"
                    aria-label="{{ __('Copy account number') }}">Copy</button>
        </div>

        {{-- Steps --}}
        <ol class="list-decimal space-y-1 rounded-lg border border-[#39b54a]/20 bg-black/20 py-3 pl-8 pr-3 text-xs leading-relaxed text-[#f5f5f0]/80">
            <li>Open <span class="font-semibold text-white">M-PESA</span> and choose <span class="font-semibold text-white">Lipa na M-PESA</span>, then <span class="font-semibold text-white">Pay Bill</span>.</li>
            <li>Business no.: <span class="font-mono font-semibold text-white">{{ $paybill }}</span></li>
            <li>Account no.: <span class="break-all font-mono font-semibold text-white">{{ $accountNo }}</span></li>
            <li>Enter the amount and your M-PESA PIN, then confirm.</li>
        </ol>

        <p class="text-[11px] leading-relaxed text-[#8fd19a]/80">
            Use your account number exactly as shown, or the money may not reach your wallet.
            A {{ \App\Livewire\Wallet\Index::EXCISE_DUTY_RATE * 100 }}% excise duty is deducted from every deposit.
        </p>
    </div>
</section>
