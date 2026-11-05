@php
    $stateLabels = [
        \App\Services\GameDisputeService::UNDER_REVIEW => 'Under review',
        \App\Services\GameDisputeService::REPORTED => 'Reported',
        \App\Livewire\Games\History::ALREADY_REPORTED => 'Already reported',
    ];
    $when = fn (?string $at) => $at ? \Illuminate\Support\Carbon::parse($at) : null;
@endphp

<div class="space-y-6" wire:init="load">

    @if ($successMessage)
        <div class="rounded-lg border border-green-700 bg-green-900/30 p-4 text-sm text-win" role="status">
            {{ $successMessage }}
        </div>
    @endif

    @if ($noticeMessage)
        <div class="rounded-lg border border-amber-600/60 bg-amber-900/30 p-4 text-sm text-amber-400" role="status">
            {{ $noticeMessage }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="mt-1 text-sm text-ash">Your latest games. Lost one unfairly? Report it within {{ $reportWindow }} of playing and our team will review it.</p>
        </div>

        <button
            type="button"
            wire:click="refresh"
            wire:loading.attr="disabled"
            wire:target="refresh, load"
            class="nt-btn nt-btn-ghost nt-btn-sm shrink-0"
        >
            <span wire:loading.remove wire:target="refresh">Refresh</span>
            <span wire:loading wire:target="refresh">Refreshing…</span>
        </button>
    </div>

    <div class="rounded-xl border border-line bg-felt-2 p-4 sm:p-6">

        <p class="mb-4 flex items-start gap-2 rounded-lg border border-line bg-felt/40 p-3 text-xs text-ash">
            <flux:icon.information-circle variant="outline" class="size-4 shrink-0 text-gold" aria-hidden="true" />
            <span>You have <strong class="text-ivory/80">{{ $reportWindow }}</strong> from the time you played to report a lost game or round. After that, the Report button disappears.</span>
        </p>

        {{-- Tabs --}}
        <div class="mb-6 flex flex-wrap gap-2" role="tablist">
            @foreach ($tabs as $key => $label)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    wire:click="setTab('{{ $key }}')"
                    wire:key="tab-{{ $key }}"
                    @class([
                        'rounded-full px-4 py-1.5 text-sm font-semibold transition',
                        'nt-btn nt-btn-gold' => $tab === $key,
                        'border border-line text-ash hover:text-ivory' => $tab !== $key,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if (! $loaded)
            <x-skeleton.region :label="__('Loading your games…')">
                <x-skeleton.rows :count="10" />
            </x-skeleton.region>
        @elseif ($loadFailed)
            <div class="py-12 text-center">
                <p class="text-sm text-hot">We could not load your games right now.</p>
                <button type="button" wire:click="refresh" class="mt-3 text-sm font-semibold text-gold hover:underline">Try again</button>
            </div>
        @elseif (empty($games[$tab]))
            <div class="py-16 text-center">
                <flux:icon.rectangle-stack variant="outline" class="mx-auto mb-3 size-10 text-ash" aria-hidden="true" />
                <p class="text-ash">No {{ strtolower($tabs[$tab]) }} played yet.</p>
            </div>
        @elseif ($tab === \App\Support\PlayedGame::GAME)

            {{-- Single games --}}
            <ul class="divide-y divide-line" wire:loading.class="opacity-60" wire:target="refresh">
                @foreach ($games[$tab] as $index => $game)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="game-{{ $index }}-{{ $game['game_wallet_id'] ?? 'row' }}">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-ivory">{{ $game['title'] }}</span>
                                <x-games.result-badge :result="$game['result']" />
                            </div>
                            <div class="mt-0.5 truncate text-xs text-ash">
                                @if ($at = $when($game['played_at']))
                                    <time datetime="{{ $at->toIso8601String() }}" title="{{ $at->format('j M Y, H:i') }}">{{ $at->diffForHumans() }}</time>
                                @endif
                                @if ($game['players'])
                                    · {{ $game['players'] }} players
                                @endif
                                @if ($game['game_id'])
                                    · <span class="font-mono">{{ $game['game_id'] }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="text-sm font-semibold text-ivory/80" title="Your stake">KES {{ number_format($game['amount'], 2) }}</span>
                            <x-games.report-action :report-key="$game['report_key']" :state="$game['report_key'] ? ($reportStates[$game['report_key']] ?? null) : null" :expires-at="$game['report_expires_at']" :labels="$stateLabels" />
                        </div>
                    </li>
                @endforeach
            </ul>
        @else

            {{-- Tournaments / jackpots, each with its rounds --}}
            <div class="space-y-4" wire:loading.class="opacity-60" wire:target="refresh">
                @foreach ($games[$tab] as $index => $competition)
                    <section class="rounded-lg border border-line p-4" wire:key="{{ $tab }}-{{ $index }}-{{ $competition['competition_wallet_id'] ?? 'row' }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-ivory">{{ $competition['title'] }}</span>
                                    @if ($competition['open'])
                                        <span class="rounded-full border border-sky-700 bg-sky-900/40 px-2 py-0.5 text-xs text-sky-300">Open</span>
                                    @else
                                        <span class="rounded-full border border-line px-2 py-0.5 text-xs text-ash">Closed</span>
                                    @endif
                                </div>
                                <div class="mt-0.5 truncate text-xs text-ash">
                                    @if ($at = $when($competition['played_at']))
                                        <time datetime="{{ $at->toIso8601String() }}" title="{{ $at->format('j M Y, H:i') }}">Joined {{ $at->diffForHumans() }}</time>
                                    @endif
                                    @if ($competition['level'] !== null)
                                        · Level {{ $competition['level'] }}
                                    @endif
                                    @if ($competition['competition_id'])
                                        · <span class="font-mono">{{ $competition['competition_id'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right text-xs">
                                <div>
                                    <span class="font-semibold text-win">{{ $competition['wins'] }} W</span>
                                    <span class="text-ash">·</span>
                                    <span class="font-semibold text-hot">{{ $competition['losses'] }} L</span>
                                </div>
                                <div class="mt-0.5 text-ash">Balance KES {{ number_format($competition['balance'], 2) }}</div>
                            </div>
                        </div>

                        @if (empty($competition['rounds']))
                            <p class="mt-3 text-xs text-ash">No rounds played yet.</p>
                        @else
                            <ul class="mt-3 divide-y divide-line border-t border-line">
                                @foreach ($competition['rounds'] as $roundIndex => $round)
                                    <li class="flex flex-wrap items-center justify-between gap-3 py-2.5" wire:key="round-{{ $tab }}-{{ $index }}-{{ $roundIndex }}-{{ $round['transaction_id'] ?? 'row' }}">
                                        <div class="flex min-w-0 items-center gap-2 text-sm">
                                            <x-games.result-badge :result="$round['result']" />
                                            @if ($round['level'] !== null)
                                                <span class="text-ivory/80">Level {{ $round['level'] }}</span>
                                            @endif
                                            @if ($at = $when($round['played_at']))
                                                <time class="text-xs text-ash" datetime="{{ $at->toIso8601String() }}" title="{{ $at->format('j M Y, H:i') }}">{{ $at->diffForHumans() }}</time>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm font-semibold text-ivory/80">KES {{ number_format($round['amount'], 2) }}</span>
                                            <x-games.report-action :report-key="$round['report_key']" :state="$round['report_key'] ? ($reportStates[$round['report_key']] ?? null) : null" :expires-at="$round['report_expires_at']" :labels="$stateLabels" />
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Report modal --}}
    <flux:modal wire:model.self="showReportModal" class="kadi-bottom-sheet sm:max-w-md">
        <div class="space-y-5 p-6">
            <div>
                <h3 class="flex items-center gap-2 text-xl font-bold text-ivory">
                    <flux:icon.flag variant="outline" class="size-5 shrink-0 text-gold" aria-hidden="true" />
                    Report a problem
                </h3>
                @if ($this->reporting)
                    <p class="mt-1 text-sm text-ash">
                        {{ $this->reporting['title'] }}
                        · KES {{ number_format($this->reporting['amount'], 2) }}
                        @if ($at = $when($this->reporting['played_at']))
                            · {{ $at->format('j M Y, H:i') }}
                        @endif
                    </p>
                @endif
                <p class="mt-2 text-xs text-ash">The winnings in question are held until our team has reviewed your report. You can report each game or round once, within {{ $reportWindow }} of playing.</p>
            </div>

            <flux:field>
                <flux:label>Reason</flux:label>
                <flux:select wire:model.live="reason" placeholder="Choose a reason">
                    @foreach ($this->reasons as $option)
                        <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="reason" />
            </flux:field>

            @if ($reason === \App\Services\GameDisputeService::OTHER_REASON)
                <flux:field>
                    <flux:label>Your reason</flux:label>
                    <flux:input wire:model="otherReason" maxlength="255" placeholder="In a few words, what went wrong?" />
                    <flux:error name="otherReason" />
                </flux:field>
            @endif

            <flux:field>
                <flux:label>Details <span class="font-normal text-ash">(optional)</span></flux:label>
                <flux:textarea wire:model="description" rows="4" maxlength="2000" placeholder="Anything that helps our team, e.g. when it happened." />
                <flux:error name="description" />
            </flux:field>

            @if ($reportError)
                <p class="text-sm font-medium text-hot" role="alert">{{ $reportError }}</p>
            @endif

            <div class="flex gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" class="w-full">Cancel</flux:button>
                </flux:modal.close>

                <flux:button
                    variant="primary"
                    class="w-full"
                    wire:click="submitReport"
                    wire:loading.attr="disabled"
                    wire:target="submitReport"
                >
                    <span wire:loading.remove wire:target="submitReport">Send report</span>
                    <span wire:loading wire:target="submitReport">Sending…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
