{{--
    The Kadi headliner (the landing hero's first slide): the headline, the real seat counts per mode
    (App\Games\KadiTables) and the published prize pool of one jackpot (config games.games.kadi.jackpots,
    the same figures as the games guide; `hero_jackpot` picks which). With no counts from the game server,
    no seat numbers are shown at all: never zeros, never estimates. The K-A-D-I hand is dealt behind the
    prize pool, which is frosted glass over it; without a configured jackpot the hand stands alone.
--}}
@props(['seats' => null])
@php
    $jackpots = config('games.games.kadi.jackpots', []);
    $jackpot = $jackpots[config('games.games.kadi.hero_jackpot')] ?? null;
    $kes = fn (int $amount) => 'KSh '.number_format($amount);
@endphp
<section @class(['nt-table', 'nt-hand-host' => ! $jackpot]) aria-labelledby="kadi-table-title">
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
            <div class="nt-prize-stage nt-hand-host">
            <x-ui.kadi-hand />
            <aside class="nt-prize" aria-labelledby="kadi-prize-title" data-prize-pool>
                <header class="nt-prize-head">
                    <div>
                        <p class="nt-prize-eyebrow">{{ $jackpot['name'] }} · <span class="nt-num">{{ $kes($jackpot['entry']) }}</span> entry</p>
                        <h2 id="kadi-prize-title">Kadi prize pool</h2>
                    </div>
                    <x-site.icon name="trophy" class="nt-prize-icon" />
                </header>
                <dl class="nt-prize-rows">
                    @foreach ($jackpot['prizes'] as [$place, $amount])
                        <div @class(['nt-prize-row', 'is-top' => $loop->first])><dt>{{ $place }}</dt><dd class="nt-num">{{ $kes($amount) }}</dd></div>
                    @endforeach
                </dl>
                <a class="nt-prize-terms" href="{{ route('game-guide') }}#jackpots" wire:navigate>Every jackpot's prizes · terms apply</a>
            </aside>
            </div>
        @else
            <x-ui.kadi-hand />
        @endif
    </div>
</section>
