{{--
    How to load the wallet by hand with M-Pesa Pay Bill. KadiApi credits a paybill payment to the customer
    whose account number matches the bill ref, so the account number shown here must be the player's own.
    The paybill is config('kadi.paybill') (MPESA_SHORTCODE). Night table panel; only the M-PESA wordmark keeps
    its brand colours, like the Google logo does.
--}}
@props(['paybill', 'accountNo'])

<section
    {{ $attributes->class('nt-panel overflow-hidden') }}
    aria-labelledby="mpesa-paybill-title"
    data-test="mpesa-paybill-card"
    x-data="{
        copied: null,
        copy(text, what) {
            navigator.clipboard?.writeText(text).then(() => { this.copied = what; setTimeout(() => this.copied = null, 2000) });
        },
    }"
>
    <div class="flex items-center gap-3 border-b border-line px-5 py-4">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-ivory text-[11px] font-black leading-none tracking-tight" aria-hidden="true">
            <span style="color: #1f9a3d">M</span><span style="color: #e4002b">-</span><span style="color: #1f9a3d">PESA</span>
        </span>
        <div class="min-w-0">
            <h3 id="mpesa-paybill-title" class="m-0 text-base font-extrabold text-ivory">Load with M-Pesa Pay Bill</h3>
            <p class="m-0 text-xs text-ash">Pay from your M-Pesa menu or the M-PESA app</p>
        </div>
    </div>

    <div class="space-y-4 px-5 py-4">
        @foreach ([['Business no.', $paybill, 'paybill', 'mpesa-paybill-number', __('Copy business number')], ['Account no.', $accountNo, 'account', 'mpesa-paybill-account', __('Copy account number')]] as [$label, $value, $key, $test, $copyLabel])
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-[11px] font-semibold uppercase tracking-widest text-ash">{{ $label }}</div>
                    <div class="nt-num break-all text-xl font-extrabold tracking-wider text-ivory" data-test="{{ $test }}">{{ $value }}</div>
                </div>
                <button type="button" x-on:click="copy(@js((string) $value), '{{ $key }}')"
                        class="nt-btn nt-btn-ghost nt-btn-sm shrink-0"
                        x-text="copied === '{{ $key }}' ? 'Copied' : 'Copy'"
                        aria-label="{{ $copyLabel }}">Copy</button>
            </div>
        @endforeach

        <ol class="m-0 list-decimal space-y-1 rounded-[var(--radius-control)] border border-line bg-felt-2 py-3 pl-8 pr-3 text-[13px] leading-relaxed text-soft">
            <li>Open <b class="text-ivory">M-PESA</b> and choose <b class="text-ivory">Lipa na M-PESA</b>, then <b class="text-ivory">Pay Bill</b>.</li>
            <li>Business no.: <b class="nt-num text-ivory">{{ $paybill }}</b></li>
            <li>Account no.: <b class="nt-num break-all text-ivory">{{ $accountNo }}</b></li>
            <li>Enter the amount and your M-PESA PIN, then confirm.</li>
        </ol>

        <p class="nt-hint">
            Use your account number exactly as shown, or the money may not reach your wallet.
            A {{ \App\Livewire\Wallet\Index::EXCISE_DUTY_RATE * 100 }}% excise duty is deducted from every deposit.
        </p>
    </div>
</section>
