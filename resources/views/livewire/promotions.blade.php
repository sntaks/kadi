<div class="nt-view-in">
    <x-ui.page-head crumb="Promotions" title="Promotions"
                    description="Every Kadi offer in one place. One wallet for Kadi, the casino and sports." />

    @if (! $welcome && ! $invite && ! $sports)
        <div class="nt-empty">
            <b>No offers running right now</b>
            Check back soon.
        </div>
    @else
        <div class="nt-offers">
            @if ($welcome)
                <section class="nt-panel nt-offer" aria-labelledby="offer-welcome" data-test="offer-welcome">
                    <span class="nt-offer-tag">Kadi · new players</span>
                    <h2 id="offer-welcome">Welcome bonus</h2>
                    <p class="nt-offer-figure"><span class="nt-num">KES {{ number_format($welcomeAmount) }}</span> in your wallet</p>
                    <p>Join with a promo code, then verify your e-mail and phone. The bonus lands in your Kadi wallet.</p>
                    <ul class="nt-offer-terms">
                        <li>Play it on Kadi games, tournaments and jackpots.</li>
                        <li>You can withdraw it once you've staked the bonus amount. Money you deposit is never locked.</li>
                        <li>Promo codes can expire, so verify soon after you join.</li>
                    </ul>
                    <div class="nt-offer-cta">
                        @if ($guest)
                            <x-ui.button :href="route('register')" data-signin="join">Join with a promo code</x-ui.button>
                        @else
                            <x-ui.button variant="ghost" :href="route('wallet')" wire:navigate>See your wallet</x-ui.button>
                        @endif
                    </div>
                </section>
            @endif

            @if ($invite)
                <section class="nt-panel nt-offer" aria-labelledby="offer-invite" data-test="offer-invite">
                    <span class="nt-offer-tag">Kadi · everyone</span>
                    <h2 id="offer-invite">Invite &amp; Earn</h2>
                    <p class="nt-offer-figure">Earn when your friends play</p>
                    <p>Share your own link or QR code. When a friend joins with it and plays, you earn referral bonuses you can withdraw to M-Pesa.</p>
                    <ul class="nt-offer-terms">
                        <li>Your friend must join with your link or code; it can't be added later.</li>
                        <li>Your earnings and withdrawals are on the Invite &amp; Earn page.</li>
                    </ul>
                    <div class="nt-offer-cta">
                        @if ($guest)
                            {{-- Account-only page: the real URL, so the sign-in sheet brings the player back to it. --}}
                            <x-ui.button variant="ghost" :href="route('referrals')" data-needs-account>Get your invite link</x-ui.button>
                        @else
                            <x-ui.button :href="route('referrals')" wire:navigate>Get your invite link</x-ui.button>
                        @endif
                    </div>
                </section>
            @endif

            @if ($sports && $multibet !== [])
                <section class="nt-panel nt-offer" aria-labelledby="offer-multibet" data-test="offer-multibet">
                    <span class="nt-offer-tag">Sports · multibets</span>
                    <h2 id="offer-multibet">Multibet bonus</h2>
                    <p class="nt-offer-figure">Up to <span class="nt-num">{{ collect($multibet)->max('percent') }}%</span> more on a winning multibet</p>
                    <p>Win a multibet and we add a bonus on your winnings: the more legs, the bigger it gets.</p>
                    <table class="nt-offer-tiers">
                        <caption class="sr-only">Multibet bonus by number of legs</caption>
                        <thead><tr><th scope="col">Winning legs</th><th scope="col">Bonus</th></tr></thead>
                        <tbody>
                            @foreach ($multibet as $i => $tier)
                                @php($next = $multibet[$i + 1]['legs'] ?? null)
                                <tr>
                                    <td class="nt-num">{{ $next === null || $next === $tier['legs'] + 1 ? $tier['legs'].($next === null ? '+' : '') : $tier['legs'].'–'.($next - 1) }}</td>
                                    <td class="nt-num">{{ $tier['percent'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <ul class="nt-offer-terms">
                        <li>Counts legs priced {{ $multibetMinPrice }} or more. The bonus is a share of your profit (winnings minus stake).</li>
                        <li>Not on free bets. Payouts stay within the maximum payout.</li>
                    </ul>
                    <div class="nt-offer-cta">
                        <x-ui.button :href="route('sports')" wire:navigate>Build a multibet</x-ui.button>
                    </div>
                </section>
            @endif

            @if ($sports)
                <section class="nt-panel nt-offer" aria-labelledby="offer-free-bets" data-test="offer-free-bets">
                    <span class="nt-offer-tag">Sports · free bets</span>
                    <h2 id="offer-free-bets">Free bets</h2>
                    @if ($freeBets->isNotEmpty())
                        <p class="nt-offer-figure">You have {{ $freeBets->count() }} {{ Str::plural('free bet', $freeBets->count()) }}</p>
                        <ul class="nt-offer-list" data-test="my-free-bets">
                            @foreach ($freeBets as $freeBet)
                                <li>
                                    <b class="nt-num">{{ \App\Sportsbook\Support\Money::format($freeBet->amount) }}</b>
                                    <span>Odds {{ $freeBet->min_odds }}+ · until {{ $freeBet->expires_at->timezone(config('sportsbook.timezone'))->format('j M, H:i') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="nt-offer-figure">A stake on us</p>
                        <p>When we give you a free bet, it shows here and in your bet slip.{{ $guest ? '' : ' You have none right now.' }}</p>
                    @endif
                    <ul class="nt-offer-terms">
                        <li>Pick it as the stake in your bet slip. Each free bet shows the total odds your slip needs.</li>
                        <li>You keep the winnings; the free stake itself isn't paid out.</li>
                    </ul>
                    <div class="nt-offer-cta">
                        <x-ui.button :variant="$freeBets->isNotEmpty() ? 'gold' : 'ghost'" :href="route('sports')" wire:navigate>Go to sports</x-ui.button>
                    </div>
                </section>
            @endif
        </div>

        <p class="nt-hint mt-2">18+ only. Bonuses are paid by the Kadi wallet, which has the final say. Play responsibly:
            set limits or take a break on <a href="{{ route('responsible-play') }}" class="underline" @guest data-needs-account @endguest>Responsible play</a>.</p>
    @endif
</div>
