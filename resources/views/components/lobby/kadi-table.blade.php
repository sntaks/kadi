{{--
    The Kadi headliner: the dealt K-A-D-I hand and the real seat counts per mode (App\Games\KadiTables).
    With no counts from the game server, no numbers are shown at all: never zeros, never estimates.
--}}
@props(['seats' => null])
<section class="nt-table nt-hand-host" aria-labelledby="kadi-table-title">
    @if ($seats && $seats['total'] > 0)
        <div class="nt-live"><i aria-hidden="true"></i><span class="nt-num">{{ number_format($seats['total']) }} {{ Str::plural('player', $seats['total']) }} at the Kadi tables now</span></div>
    @endif
    <h1 id="kadi-table-title">Kadi tables are open</h1>
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
    <x-ui.kadi-hand />
</section>
