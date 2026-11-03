{{-- The deposit sheet (App\Livewire\Wallet\DepositSheet): right drawer on desktop, bottom sheet on phones. --}}
<div x-data="{ open: $wire.entangle('open') }" x-on:keydown.escape.window="if (open) $wire.close()" data-sheet="deposit">
    <div class="nt-scrim" x-show="open" x-cloak x-transition.opacity x-on:click="$wire.close()"></div>
    <div role="dialog" aria-modal="true" aria-labelledby="deposit-title" class="nt-sheet"
         x-show="open" x-cloak x-trap.inert.noscroll="open"
         x-transition:enter="nt-sheet-anim" x-transition:enter-start="nt-sheet-from" x-transition:enter-end=""
         x-transition:leave="nt-sheet-anim" x-transition:leave-start="" x-transition:leave-end="nt-sheet-from">
        <button type="button" class="nt-icon-btn nt-sheet-close" aria-label="Close" wire:click="close">
            <x-site.icon name="x" />
        </button>
        <div class="nt-sheet-inner">
            <h2 id="deposit-title">Deposit with M-Pesa</h2>

            @if ($stage === 'sent')
                <div wire:poll.5s="check" class="nt-deposit-state" role="status">
                    <p><b>Check your phone.</b> We've sent an M-Pesa request{{ $phone ? ' to '.$phone : '' }}. Enter your M-Pesa PIN to approve it.</p>
                    <p class="nt-hint">Your balance updates here as soon as the money arrives.</p>
                </div>
                <x-ui.button variant="ghost" block wire:click="close">Close</x-ui.button>
            @elseif ($stage === 'received')
                <div class="nt-deposit-state" role="status"><p><b>Deposit received.</b> Your balance is up to date.</p></div>
                <x-ui.button block wire:click="close">Done</x-ui.button>
            @elseif ($stage === 'waiting')
                <div class="nt-deposit-state" role="status">
                    <p><b>Not received yet.</b> If you approved the request, your balance updates as soon as M-Pesa confirms it.</p>
                </div>
                <x-ui.button variant="ghost" block wire:click="close">Close</x-ui.button>
            @else
                <p class="nt-hint">{{ $phone ? "We'll send a request to {$phone}. Approve it with your M-Pesa PIN." : 'Confirm your M-Pesa number first; we send the request to it.' }}</p>

                <form wire:submit="send" class="mt-4 grid gap-4">
                    <div class="nt-chips" role="group" aria-label="Quick amounts">
                        @foreach ($amounts as $quick)
                            <button type="button" class="nt-chip" wire:click="pick({{ $quick }})" aria-pressed="{{ (string) $quick === $amount ? 'true' : 'false' }}">KES {{ number_format($quick) }}</button>
                        @endforeach
                    </div>

                    <label class="nt-field">
                        <span>Amount (KES)</span>
                        <input type="text" inputmode="numeric" wire:model.live.debounce.300ms="amount" autocomplete="off"
                               aria-describedby="deposit-breakdown @if ($error) deposit-error @endif">
                    </label>

                    <dl id="deposit-breakdown" class="nt-breakdown">
                        <div><dt>Excise duty (5%)</dt><dd class="nt-num">KES {{ number_format($breakdown['excise'], 2) }}</dd></div>
                        <div><dt>Reaches your wallet</dt><dd class="nt-num"><b>KES {{ number_format($breakdown['credited'], 2) }}</b></dd></div>
                    </dl>

                    @if ($error)
                        <p id="deposit-error" class="nt-error" role="alert">{{ $error }}</p>
                    @endif

                    <x-ui.button type="submit" size="lg" block wire:loading.attr="disabled" wire:target="send">
                        <span wire:loading.remove wire:target="send">Send request to my phone</span>
                        <span wire:loading wire:target="send">Sending…</span>
                    </x-ui.button>
                    <p class="nt-hint text-center">Smallest deposit KES {{ $min }}. Deposit limits you set apply.</p>
                </form>
            @endif
        </div>
    </div>
</div>
