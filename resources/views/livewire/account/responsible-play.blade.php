<x-account.frame active="responsible-play">
    <div class="nt-area" wire:init="load">
        <div class="nt-stack">
            {{-- Where the player stands --}}
            <section class="nt-panel nt-card-pad" aria-labelledby="rp-status">
                <h2 id="rp-status">Responsible play</h2>
                <p>Set deposit limits or take a break whenever you want to. They cover every game: Kadi, slots and scratch cards.</p>

                @if (! $linked)
                    <p class="nt-hint">Your wallet is still being set up. Limits and breaks are available once it's ready.</p>
                @elseif (! $loaded)
                    <x-skeleton class="h-5 w-64 rounded" />
                @elseif ($breakEndsAt)
                    <div class="nt-callout" role="status" data-test="rp-on-break">
                        <span><b>You're on a break until {{ $breakEndsAt->format('j F Y, H:i') }}.</b>
                            You can still log in and withdraw. Games and deposits open again when it ends.</span>
                    </div>
                @elseif ($current === null)
                    <p class="nt-error" role="status">We couldn't load your limits just now. <button type="button" class="underline" wire:click="$refresh">Try again</button></p>
                @else
                    <x-ui.pill tone="ok">No break running</x-ui.pill>
                @endif
            </section>

            {{-- Deposit limits --}}
            @if ($linked)
                <section class="nt-panel nt-card-pad" aria-labelledby="rp-limits">
                    <h2 id="rp-limits">Deposit limits</h2>
                    <p>The most you can deposit in a rolling day, week or month. Lowering a limit works at once; raising or removing one takes 24 hours.</p>

                    <div class="nt-limits mb-5">
                        @foreach ($periods as $key => $label)
                            @php($limit = $limits->get($key))
                            <div class="nt-limit">
                                <h3>{{ $label }}</h3>
                                @if (! $loaded)
                                    <x-skeleton class="h-6 w-24 rounded" />
                                @elseif ($limit && $limit['amount'] !== null)
                                    <div class="nt-limit-now nt-num">KES {{ number_format($limit['amount']) }}</div>
                                    @if (isset($limit['remaining']))<p class="nt-hint">KES {{ number_format($limit['remaining']) }} left now</p>@endif
                                @else
                                    <div class="nt-limit-now">No limit</div>
                                @endif
                                @if ($limit && $limit['pending_from'])
                                    <p class="nt-hint mt-1">
                                        {{ $limit['pending_amount'] === null ? 'Removed' : 'Becomes KES '.number_format($limit['pending_amount']) }}
                                        on {{ \Carbon\CarbonImmutable::parse($limit['pending_from'])->timezone(config('app.timezone'))->format('j M, H:i') }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <form wire:submit="saveLimit" class="grid gap-4 sm:grid-cols-[180px_minmax(0,1fr)_auto] sm:items-end">
                        <label class="nt-field">
                            <span>Period</span>
                            <select wire:model="period">
                                @foreach ($periods as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                        </label>
                        <label class="nt-field">
                            <span>Limit (KES)</span>
                            <input type="text" inputmode="numeric" wire:model="limitAmount" placeholder="Leave empty to remove" autocomplete="off">
                        </label>
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveLimit">Save limit</x-ui.button>
                    </form>
                    @if ($limitError)<p class="nt-error mt-3" role="alert">{{ $limitError }}</p>@endif
                    @if ($limitSaved)<p class="nt-ok mt-3" role="status">{{ $limitSaved }}</p>@endif
                </section>

                {{-- Breaks --}}
                <section class="nt-panel nt-card-pad" aria-labelledby="rp-break">
                    <h2 id="rp-break">Take a break</h2>
                    <p>During a break you can't play or deposit, on any game. You can still log in and withdraw. A break can be made longer, but it can't be shortened or ended early, even by our support team.</p>

                    @if ($breakStarted)<p class="nt-ok mb-4" role="status">{{ $breakStarted }}</p>@endif

                    @if ($confirmingBreak && $chosen)
                        <div class="nt-callout" role="alertdialog" aria-labelledby="rp-confirm">
                            <span id="rp-confirm"><b>Start a {{ $chosen }} {{ $chosenIsSelfExclusion ? 'self-exclusion' : 'break' }} now?</b>
                                You won't be able to play or deposit until it ends, and you can't undo it.</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-ui.button wire:click="startBreak" wire:loading.attr="disabled" wire:target="startBreak">Yes, start my break</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="cancelBreak">Go back</x-ui.button>
                        </div>
                    @else
                        <form wire:submit="reviewBreak" class="grid gap-5">
                            <fieldset class="grid gap-2">
                                <legend class="mb-2 text-sm font-semibold text-soft">A short break</legend>
                                <div class="nt-options">
                                    @foreach ($breaks['cool_off'] as $duration => $label)
                                        <label class="nt-option"><input type="radio" wire:model="breakChoice" value="cool_off:{{ $duration }}"><span>{{ $label }}</span></label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <fieldset class="grid gap-2">
                                <legend class="mb-2 text-sm font-semibold text-soft">Self-exclusion</legend>
                                <div class="nt-options">
                                    @foreach ($breaks['self_exclusion'] as $duration => $label)
                                        <label class="nt-option"><input type="radio" wire:model="breakChoice" value="self_exclusion:{{ $duration }}"><span>{{ $label }}</span></label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div><x-ui.button type="submit" variant="ghost">Continue</x-ui.button></div>
                        </form>
                    @endif
                    @if ($breakError)<p class="nt-error mt-3" role="alert">{{ $breakError }}</p>@endif
                </section>
            @endif
        </div>

        <aside class="nt-stack" aria-label="Help">
            <div class="nt-panel nt-card-pad">
                <h2 class="nt-card-title">Need to talk to someone?</h2>
                <p class="nt-lead mt-2">If gambling stops being fun, take a break and talk to someone you trust. Our support team can help with limits and breaks.</p>
                @if ($whatsapp = \App\Support\Contact::whatsapp())
                    <x-ui.button variant="ghost" :href="$whatsapp" target="_blank" rel="noopener noreferrer">Chat on WhatsApp<span class="sr-only"> (opens in a new tab)</span></x-ui.button>
                @endif
            </div>
            <div class="nt-panel nt-card-pad">
                <h2 class="nt-card-title">18+ only</h2>
                <p class="nt-lead mt-2 mb-0">Kadi is only for players aged 18 and over. Stake only what you can afford to lose.</p>
            </div>
        </aside>
    </div>
</x-account.frame>
