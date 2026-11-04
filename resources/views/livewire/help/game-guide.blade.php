{{--
    Kadi game modes and prizes in the reading column (Phase 6b). The copy and every payout table are the
    old page's; the stake tabs became plain tables so all of it is readable at once and crawlable.
--}}
@php
    $kes = fn ($amount) => 'KSh '.(is_string($amount) ? $amount : number_format($amount));
    $singles = [
        ['2-player match', 'Winner takes all: 1.9× your stake', [[20, 38], [50, 95], [100, 190], [250, 475], [500, 950], [1000, 1900]]],
        ['3-player match', 'Outplay two rivals: 2.85× your stake', [[20, '57.00'], [50, '142.50'], [100, '285.00'], [250, '712.50'], [500, '1,425.00'], [1000, '2,850.00']]],
        ['4-player match', 'Dominate the table: 3.8× your stake', [[20, 76], [50, 190], [100, 380], [250, 950], [500, 1900], [1000, 3800]]],
    ];
    $tournaments = [
        ['Emerald', '3 rounds', '×7.2', [[20, 144], [50, 360], [100, 720], [250, 1800], [500, 3600], [1000, 7200]]],
        ['Ruby', '4 rounds', '×14.4', [[20, 288], [50, 720], [100, 1440], [250, 3600], [500, 7200], [1000, 14400]]],
        ['Diamond', '5 rounds', '×28.8', [[20, 576], [50, 1440], [100, 2880], [250, 7200], [500, 14400], [1000, 28800]]],
    ];
    $jackpots = [
        ['Bronze jackpot', 'KSh 20', '13 games', [['1st place', '86,291'], ['Runner-up', '19,544'], ['Semis (2)', '7,795'], ['Quarters (4)', '2,441']], '131,189'],
        ['Silver jackpot', 'KSh 50', '17 games', [['1st place', '3,451,655'], ['Runner-up', '781,760'], ['Semis (2)', '311,830'], ['Quarters (4)', '96,449']], '5,242,871'],
        ['Gold jackpot', 'KSh 100', '21 games', [['1st place', '110,452,969'], ['Runner-up', '25,016,339'], ['Semis (2)', '9,978,584'], ['Quarters (4)', '3,086,378']], '167,772,160'],
    ];
    $toc = [['how-it-works', 'How it works'], ['single', 'Single games'], ['tournaments', 'Tournaments'], ['jackpots', 'Jackpots']];
@endphp
<x-help.page title="Kadi game modes & prizes" crumb="Help · Game modes & prizes"
             intro="Play. Win. Repeat. From head-to-head Kadi singles to high-stakes jackpots, pick your game, place your bet, and take home real winnings."
             :toc="$toc">

    <section id="how-it-works" data-help-section>
        <h2>How it works</h2>
        <p>Every ticket follows the same simple idea, whether it's a quick single game or a multi-round Jackpot.</p>
        <ol>
            <li><strong>Buy a ticket.</strong> One ticket is one attempt: a single game, a tournament run, or a Jackpot campaign. No extra lives: lose it, and you buy a fresh ticket to try again.</li>
            <li><strong>Win to advance.</strong> Single games finish in one match. Tournaments and Jackpots move you forward one 1 vs 1 win at a time, level by level.</li>
            <li><strong>Progress is saved.</strong> Stepped away mid-tournament or mid-Jackpot? No problem, your level is remembered. Come back in an hour or next week and you'll face other players at your same stage.</li>
            <li><strong>Claim your prize.</strong> Reach the final stage and the prize is yours, paid straight into your wallet.</li>
        </ol>
    </section>

    <section id="single" data-help-section>
        <p class="nt-kicker">Head to head</p>
        <h2>Single games</h2>
        <p>Challenge a single opponent. Pick your stake, win big. The more players at the table, the higher the payout.</p>
        <p>One ticket, one game. Win or lose, grab another ticket anytime to play again.</p>
        @foreach ($singles as [$name, $line, $rows])
            <h3>{{ $name }}</h3>
            <p>{{ $line }}</p>
            <div class="nt-table-wrap">
                <table>
                    <thead><tr><th scope="col">Stake</th><th scope="col" class="text-right">Winner gets</th></tr></thead>
                    <tbody>
                        @foreach ($rows as [$stake, $win])
                            <tr><td class="nt-num">{{ $kes($stake) }}</td><td class="nt-num text-right">{{ $kes($win) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </section>

    <section id="tournaments" data-help-section>
        <h2>Tournaments</h2>
        <p>Battle through multiple rounds of elimination. The deeper you go, the bigger the multiplier, up to ×28.8.</p>
        <p>Win a round to advance. Stop anytime: your progress saves, and you'll return to players at your same level.</p>
        @foreach ($tournaments as [$name, $rounds, $multiplier, $rows])
            <h3>{{ $name }} <span class="nt-hint">· {{ $rounds }} · {{ $multiplier }}</span></h3>
            <div class="nt-table-wrap">
                <table>
                    <thead><tr><th scope="col">Stake</th><th scope="col" class="text-right">Wins</th></tr></thead>
                    <tbody>
                        @foreach ($rows as [$stake, $win])
                            <tr><td class="nt-num">{{ $kes($stake) }}</td><td class="nt-num text-right">{{ $kes($win) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </section>

    <section id="jackpots" data-help-section>
        <h2>Jackpots</h2>
        <p>Multi-table elimination brackets with massive prize pools. Entry fees fund the entire prize structure.</p>
        <p>Play at your own pace. Progress saves between games, so pick up right where you left off.</p>
        @foreach ($jackpots as [$name, $entry, $games, $rows, $total])
            <h3>{{ $name }} <span class="nt-hint">· entry {{ $entry }} · {{ $games }}</span></h3>
            <div class="nt-table-wrap">
                <table>
                    <thead><tr><th scope="col">Category</th><th scope="col" class="text-right">Total prize</th></tr></thead>
                    <tbody>
                        @foreach ($rows as [$category, $prize])
                            <tr><td>{{ $category }}</td><td class="nt-num text-right">{{ $kes($prize) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><th scope="row">Total</th><td class="nt-num text-right"><strong>{{ $kes($total) }}</strong></td></tr></tfoot>
                </table>
            </div>
        @endforeach
    </section>

    <section aria-labelledby="ready">
        <h2 id="ready">Pick your game. Place your bet.</h2>
        <p>From quick singles to massive jackpots, there's a game mode for every player and every budget.</p>
        <div class="flex flex-wrap gap-2">
            @auth
                <x-play-kadi class="nt-btn nt-btn-gold">Play Kadi</x-play-kadi>
            @else
                <x-ui.button :href="route('register')" data-signin="join">Join Kadi</x-ui.button>
                <x-ui.button variant="ghost" :href="route('login')" data-signin="login">Log in</x-ui.button>
            @endauth
        </div>
    </section>
</x-help.page>
