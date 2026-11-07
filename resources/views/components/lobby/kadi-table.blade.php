{{--
    The Kadi headliner (the landing hero's first slide): the headline, the real seat counts per mode
    (App\Games\KadiTables) and the published prize pool of one jackpot (config games.games.kadi.jackpots,
    the same figures as the games guide; `hero_jackpot` picks which). With no counts from the game server,
    no seat numbers are shown at all: never zeros, never estimates. The prize pool follows the old site's
    design (a medal per place in its own colour, glowing amounts that count up once on view) in this theme's
    tokens; the real figures are in the HTML, the count-up only replays them.
--}}
@props(['seats' => null])
@php
    $jackpots = config('games.games.kadi.jackpots', []);
    $jackpot = $jackpots[config('games.games.kadi.hero_jackpot')] ?? null;
    $kes = fn (int $amount) => 'KSh '.number_format($amount);
    // Medal and colour per place, as on the old site: gold, silver, bronze, then blue for the quarter-finals.
    $places = [['medal-first-place', 'gold'], ['medal-second-place', 'silver'], ['medal-third-place', 'bronze'], ['target-02', 'blue']];
@endphp
<section class="nt-table" aria-labelledby="kadi-table-title">
    <div class="nt-table-body">
        <div class="nt-table-copy">
            @if ($seats && $seats['total'] > 0)
                <div class="nt-live"><i aria-hidden="true"></i><span class="nt-num">{{ number_format($seats['total']) }} {{ Str::plural('player', $seats['total']) }} at the Kadi tables now</span></div>
            @endif
            <h1 id="kadi-table-title" class="nt-table-head">Fortune Favors the Bold</h1>
            <p>Kenya's card game, head to head for real money. Sit down at a single game, join a tournament or chase a jackpot.</p>
            @if ($seats)
                <dl class="nt-seats" aria-label="Players at the Kadi tables now">
                    <div><dt class="sr-only">Single games</dt><dd class="m-0"><b class="nt-num">{{ number_format($seats['single']) }}</b><span>in single games</span></dd></div>
                    <div><dt class="sr-only">Tournaments</dt><dd class="m-0"><b class="nt-num">{{ number_format($seats['tournaments']) }}</b><span>in tournaments</span></dd></div>
                    <div><dt class="sr-only">Jackpots</dt><dd class="m-0"><b class="nt-num">{{ number_format($seats['jackpots']) }}</b><span>chasing jackpots</span></dd></div>
                </dl>
            @endif
            <div class="nt-ctas">
                @auth
                    <x-play-kadi class="nt-btn nt-btn-gold nt-btn-lg">Play Kadi</x-play-kadi>
                @else
                    <x-ui.button size="lg" :href="route('register')" data-signin="join">Join and play</x-ui.button>
                @endauth
                <x-ui.button variant="ghost" size="lg" :href="route('rules')" wire:navigate>How to play</x-ui.button>
            </div>
        </div>

        @if ($jackpot)
            <aside class="nt-prize" aria-labelledby="kadi-prize-title" data-prize-pool>
                <header class="nt-prize-head">
                    <div>
                        <p class="nt-prize-eyebrow">{{ $jackpot['name'] }} · <span class="nt-num">{{ $kes($jackpot['entry']) }}</span> entry</p>
                        <h2 id="kadi-prize-title">Kadi Prize Pool</h2>
                    </div>
                    <x-site.icon name="trophy" class="nt-prize-icon" />
                </header>
                <dl class="nt-prize-rows" x-data="prizeCount">
                    @foreach ($jackpot['prizes'] as [$place, $amount])
                        @php [$medal, $tone] = $places[$loop->index] ?? end($places); @endphp
                        <div @class(['nt-prize-row', 'is-top' => $loop->first]) data-tone="{{ $tone }}">
                            <dt><span class="nt-prize-medal" aria-hidden="true">@svg('hugeicons-'.$medal)</span>{{ $place }}</dt>
                            <dd class="nt-num" data-count-to="{{ $amount }}" data-count-final="{{ $kes($amount) }}">{{ $kes($amount) }}</dd>
                        </div>
                    @endforeach
                </dl>
                <a class="nt-prize-terms" href="{{ route('game-guide') }}#jackpots" wire:navigate>Tournament terms apply</a>
            </aside>
        @endif
    </div>
</section>
