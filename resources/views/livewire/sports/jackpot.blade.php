@php use App\Sportsbook\Support\Money; use App\Sportsbook\Enums\JackpotEntryStatus; @endphp
<div class="nt-sports nt-view-in">
    @if ($this->jackpot === null)
        <x-ui.page-head title="Sports jackpot" crumb="Football" />
        <div class="nt-panel nt-empty nt-sb-pad"><b>No jackpot is open right now</b>When a jackpot opens, you can predict every match here.</div>
    @else
        @php
            $jackpot = $this->jackpot;
            $matches = $jackpot->jackpotFixtures;
            $tz = config('sportsbook.timezone');
        @endphp

        <x-ui.page-head :title="$jackpot->name" crumb="Sports jackpot"
                        :description="'Predict all '.$matches->count().' matches for '.Money::format($jackpot->entry_price).'. Each prize is shared equally by every entry with that many right; a void match counts as right for everyone.'">
            <x-slot:actions>
                @if ($this->isTakingEntries)
                    <div class="nt-countdown" x-data="{
                            closesAt: {{ $this->closesAt->getTimestampMs() }}, left: '', timer: null,
                            init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000) },
                            destroy() { clearInterval(this.timer) },
                            tick() {
                                const s = Math.max(0, Math.floor((this.closesAt - Date.now()) / 1000));
                                const d = Math.floor(s / 86400);
                                this.left = (d > 0 ? d + 'd ' : '') + [Math.floor(s / 3600) % 24, Math.floor(s / 60) % 60, s % 60].map((p) => String(p).padStart(2, '0')).join(':');
                            },
                         }" data-test="jackpot-countdown">
                        <span>Entries close in</span>
                        <b class="nt-num" x-text="left">{{ $this->closesAt->timezone($tz)->format('D j M, H:i') }}</b>
                    </div>
                @else
                    <span class="nt-pill">Entries closed</span>
                @endif
            </x-slot:actions>
        </x-ui.page-head>

        <ul class="nt-tiers" data-test="prize-tiers">
            @foreach ($jackpot->prize_tiers as $tier)
                <li @class(['nt-panel', 'nt-tier-top' => $loop->first])>
                    <span>{{ $tier['correct'] }} of {{ $matches->count() }} right</span>
                    <b class="nt-num">{{ Money::format($tier['pool']) }}</b>
                </li>
            @endforeach
        </ul>

        @if ($entry = $this->enteredEntry)
            <section class="nt-panel nt-jp-done" role="status" data-test="jackpot-entered"
                     @if ($entry->status === JackpotEntryStatus::Pending) wire:poll.3s="refreshEntry" @endif>
                @if ($entry->status === JackpotEntryStatus::Pending)
                    <b>Checking your entry…</b>
                    <p>Your wallet hasn't answered yet. We're checking, and nothing will be taken twice.</p>
                @elseif ($entry->status === JackpotEntryStatus::Rejected)
                    <b>Your entry wasn't made</b>
                    <p>Nothing was taken from your wallet. You can try again.</p>
                @else
                    <b>You're in!</b>
                    <p>Entry <span class="nt-num">{{ $entry->reference }}</span> · {{ Money::format($entry->stake) }}</p>
                @endif
                <div class="nt-actions-row">
                    @if (Route::has('activity'))
                        <a class="nt-btn nt-btn-ghost" href="{{ route('activity', ['game' => 'sports', 'show' => 'jackpot']) }}" wire:navigate>See my entries</a>
                    @endif
                    @if ($this->isTakingEntries && $entry->status !== JackpotEntryStatus::Pending)
                        <button type="button" class="nt-btn nt-btn-gold" wire:click="enterAgain">Enter again</button>
                    @endif
                </div>
            </section>
        @else
            <section class="nt-panel nt-jp"
                     x-data="{
                        picks: {},
                        ids: @js($matches->pluck('id')),
                        pick(id, outcome) { this.picks[id] = this.picks[id] === outcome ? undefined : outcome; },
                        autoPick() { this.ids.forEach((id) => this.picks[id] = ['1', 'X', '2'][Math.floor(Math.random() * 3)]); },
                        clear() { this.picks = {}; },
                        get count() { return this.ids.filter((id) => this.picks[id]).length; },
                     }"
                     x-on:jackpot-entered.window="clear()">
                <header class="nt-jp-head">
                    <p><b class="nt-num" x-text="count">0</b> of {{ $matches->count() }} picked</p>
                    @if ($this->isTakingEntries)
                        <button type="button" class="nt-btn nt-btn-ghost nt-btn-sm" x-on:click="autoPick()" data-test="auto-pick">Auto-pick</button>
                        <button type="button" class="nt-btn nt-btn-quiet nt-btn-sm" x-on:click="clear()">Clear</button>
                    @endif
                </header>

                <ol class="nt-jp-list">
                    @foreach ($matches as $match)
                        <li wire:key="jackpot-match-{{ $match->id }}">
                            <span class="nt-jp-match">
                                <b><span class="nt-num">{{ $match->position }}.</span> {{ $match->fixture_name }}</b>
                                <span class="nt-meta nt-num">{{ $match->kicks_off_at->timezone($tz)->format('D j M, H:i') }}
                                    @if ($match->is_void) · Void @elseif ($match->outcome !== null) · Result {{ $match->outcome }} @endif
                                </span>
                            </span>
                            <span class="nt-jp-picks" role="group" aria-label="{{ $match->fixture_name }}">
                                @foreach (['1', 'X', '2'] as $outcome)
                                    <button type="button" class="nt-odds" aria-label="{{ $match->fixture_name }}: {{ $outcome }}"
                                            @if ($this->isTakingEntries)
                                                aria-pressed="false"
                                                x-bind:aria-pressed="picks[{{ $match->id }}] === '{{ $outcome }}' ? 'true' : 'false'"
                                                x-on:click="pick({{ $match->id }}, '{{ $outcome }}')"
                                            @else disabled @endif><b>{{ $outcome }}</b></button>
                                @endforeach
                            </span>
                        </li>
                    @endforeach
                </ol>

                @if ($this->isTakingEntries)
                    <footer class="nt-jp-foot">
                        @error('entry')
                            <p class="nt-slip-error" role="alert" data-test="entry-error">{{ $message }}
                                @if ($refusal === \App\Sportsbook\Betting\Jackpot\JackpotEntryRefused::INSUFFICIENT_FUNDS)
                                    <button type="button" x-on:click="$dispatch('open-deposit')">Deposit</button>
                                @endif
                            </p>
                        @enderror
                        @auth
                            @if ($this->myEntryCount > 0)
                                <p class="nt-meta">You have {{ $this->myEntryCount }} {{ Str::plural('entry', $this->myEntryCount) }} in this jackpot.</p>
                            @endif
                            <button type="button" class="nt-btn nt-btn-gold nt-btn-lg nt-btn-block"
                                    x-on:click="$wire.enter(picks)" x-bind:disabled="count < ids.length"
                                    wire:loading.attr="disabled" wire:target="enter" data-test="enter-jackpot">
                                <span wire:loading.remove wire:target="enter">Enter for {{ Money::format($jackpot->entry_price) }}</span>
                                <span wire:loading wire:target="enter">Checking your entry…</span>
                            </button>
                        @else
                            <a class="nt-btn nt-btn-gold nt-btn-lg nt-btn-block" href="{{ route('login') }}" data-signin>Log in to enter</a>
                        @endauth
                    </footer>
                @endif
            </section>
        @endif
    @endif
</div>
