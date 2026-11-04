<x-account.frame active="activity">
    <section class="nt-panel" aria-labelledby="activity-title">
        <div class="nt-list-head">
            <h2 id="activity-title">Activity</h2>
            <div class="nt-tabs" role="tablist" aria-label="Game">
                @foreach ($tabs as $key => $label)
                    <button type="button" role="tab" class="nt-tab" wire:click="show('{{ $key }}')"
                            aria-selected="{{ $tab === $key ? 'true' : 'false' }}" data-test="activity-tab-{{ $key }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        @if ($tab === 'kadi')
            {{-- Kadi's own history: latest games, tournaments and jackpots, with the tools to report one. --}}
            <div class="px-[22px] pb-6 max-[640px]:px-4">
                <livewire:games.history :embedded="true" :key="'kadi-history'" />
            </div>
        @elseif ($tab === 'sports')
            @include('livewire.account.partials.sports-bets', ['page' => $sports])
        @else
            <div wire:init="load" wire:key="wagers-{{ $tab }}-{{ $page }}">
                @if (! $loaded)
                    <x-skeleton.rows :count="5" class="px-[22px] pb-6" />
                @elseif ($list === null)
                    <div class="nt-empty px-[22px]" role="status"><b>We couldn't load your {{ strtolower($tabs[$tab]) }} just now</b>
                        <x-ui.button variant="ghost" size="sm" class="mt-3" wire:click="$refresh">Try again</x-ui.button></div>
                @elseif ($list['items'] === [])
                    <div class="nt-empty px-[22px]"><b>Nothing here yet</b>{{ $tab === 'slots' ? 'Every spin' : 'Every scratch card' }} you play shows up here, newest first.</div>
                @else
                    <ul class="nt-list">
                        @foreach ($list['items'] as $item)
                            <li class="nt-row" wire:key="wager-{{ $item['reference'] }}">
                                <span class="nt-row-icon" aria-hidden="true"><x-site.icon :name="$tab === 'slots' ? 'slot' : 'gift'" /></span>
                                <span class="min-w-0">
                                    <b>{{ $item['name'] }}</b>
                                    <span class="nt-meta">
                                        {{ $item['placed_at']?->timezone(config('app.timezone'))->format('j M, H:i') ?? '' }}
                                        · Stake KES {{ number_format($item['stake'], 2) }}{{ $item['free_bet'] ? ' (free bet)' : '' }}
                                    </span>
                                </span>
                                <span class="nt-trail nt-num">
                                    @if ($item['status'] === 'won')
                                        <span class="nt-in">+KES {{ number_format($item['payout'], 2) }}</span>
                                        @if ($item['tax'] > 0)<span class="nt-meta">after KES {{ number_format($item['tax'], 2) }} tax</span>@endif
                                    @else
                                        <span class="nt-meta">{{ $item['outcome'] }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($list['last_page'] > 1)
                        <div class="nt-list-foot">
                            <span>Page {{ $list['page'] }} of {{ $list['last_page'] }}</span>
                            <span class="flex gap-2">
                                <x-ui.button variant="ghost" size="sm" wire:click="goTo({{ $list['page'] - 1 }})" :disabled="$list['page'] <= 1">Newer</x-ui.button>
                                <x-ui.button variant="ghost" size="sm" wire:click="goTo({{ $list['page'] + 1 }})" :disabled="$list['page'] >= $list['last_page']">Older</x-ui.button>
                            </span>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </section>
</x-account.frame>
