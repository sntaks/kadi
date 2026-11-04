{{--
    How to play (the Kadi rules) in the reading column (Phase 6b). The copy is the old page's, word for word;
    the six card types are plain sections instead of interactive tabs, so all of it is readable and crawlable.
--}}
@php
    $cardTypes = [
        ['Play Cards', '4, 5, 6, 7, 9, 10', 'The backbone of every hand. Play a card that matches either the suit or the rank of the top card on the discard pile. No special effects, just tempo and timing. Hold several matching cards? Chain them in one turn, for example 4♠ → 4♥ → 4♦ → 4♣.'],
        ['Jump Cards', 'Jack (J)', "Skip the next player's turn entirely, provided the Jack matches the suit or rank of the top card. Perfect for cutting off an opponent who's about to call Kadi."],
        ['Question Cards', 'Queen (Q), 8', "Puts the next player on the spot. They must answer with a matching Play Card (4, 5, 6, 7, 9 or 10) of the same suit or rank. Fail to answer and it's straight to the draw pile. Queens and 8s can chain onto each other, but only when they share the same rank."],
        ['Kickback Cards', 'King (K)', 'Reverses the direction of play instantly: clockwise becomes counter-clockwise and vice versa. A single King can flip the entire momentum of the table.'],
        ['Penalty Cards', '2, 3, Joker', 'Force the next player to draw 2, 3, or 5 cards. Chain a matching Penalty Card to pass it along instead of drawing, or neutralize it entirely with an Ace. The black Joker matches Spades & Clubs, and the red Joker matches Hearts & Diamonds. See the chaining rules below for every valid combo.'],
        ['Ace Cards', 'Ace (A)', 'The most versatile card in the deck. Declare a brand new suit for the next player to follow, or use it to neutralize an incoming penalty. Pure strategic control.'],
    ];
    $toc = [['objective', 'Objective'], ['setup', 'Setup & dealing'], ['card-types', 'Card types'], ['chaining', 'Chaining'], ['gameplay', 'Gameplay'], ['winning', 'Winning'], ['strategy', 'Strategy']];
@endphp
<x-help.page title="Kadi rules: how to play" crumb="Help · The official rulebook"
             intro="Four suits. Six card types. One winner. Learn how every hand of Kadi is dealt, played, and won, then take it to the table."
             :toc="$toc">

    <section id="objective" data-help-section>
        <p class="nt-kicker">The objective</p>
        <h2>Empty your hand first</h2>
        <p>Kadi is a fast-paced Kenyan card game for 2-4 players, played with a standard 54-card deck (Jokers included).
            Every hand starts even (4 cards each), and the first player to strategically play their <strong>entire hand</strong>
            while outmaneuvering opponents' action cards claims victory.</p>
    </section>

    <section id="setup" data-help-section>
        <h2>Setup &amp; dealing</h2>
        <ol>
            <li><strong>Shuffle &amp; deal.</strong> The server shuffles a full 54-card deck (with two Jokers) and deals 4 cards to each player.</li>
            <li><strong>The draw pile.</strong> Remaining cards form a face-down draw pile, managed automatically by the server.</li>
            <li><strong>Start the discard.</strong> The top card of the draw pile is flipped face-up to begin the discard pile.</li>
            <li><strong>Opening restrictions.</strong> 2s, 3s, Jokers, Queens, 8s, Jacks, Kings &amp; Aces can never start the discard pile; they're auto-replaced.</li>
        </ol>
    </section>

    <section id="card-types" data-help-section>
        <h2>Card suits &amp; classifications</h2>
        <p>Every card falls into one of six categories.</p>
        @foreach ($cardTypes as [$label, $ranks, $text])
            <h3>{{ $label }} <span class="nt-hint">· {{ $ranks }}</span></h3>
            <p>{{ $text }}</p>
        @endforeach
    </section>

    <section id="chaining" data-help-section>
        <h2>Chaining &amp; combos</h2>
        <p>A single turn isn't limited to one card. Play several compatible cards back to back. Here's what's allowed.</p>
        <h3>Standard chains</h3>
        <p>Each card must match the one before it. A chain ends when you run out of compatible cards, choose to stop, or a card effect forces it to end.
            For example <span class="nt-cards">4♠ → 4♥ → 4♦ → 4♣</span>.</p>
        <h3>Question chains</h3>
        <p>Queens and 8s can be chained onto each other, but only when they share the same rank. Once a valid Answer Card is played, the Question ends.
            For example <span class="nt-cards">Q♠ → Q♥ → 7♥</span>.</p>
        <h3>Penalty chains</h3>
        <p>Pass a penalty on instead of drawing. Only the most recently played Penalty Card's effect applies to whoever can't continue the chain.</p>
        <ul>
            <li>2 → 2</li>
            <li>3 → 3</li>
            <li>2 or 3 → matching same-suit 2 or 3</li>
            <li>Joker → Joker</li>
            <li>Red Joker ↔ red 2 or 3</li>
            <li>Black Joker ↔ black 2 or 3</li>
        </ul>
    </section>

    <section id="gameplay" data-help-section>
        <h2>Gameplay mechanics</h2>
        <ol>
            <li><strong>Play moves clockwise.</strong> Turns begin next to the dealer and proceed clockwise, until a King reverses the direction.</li>
            <li><strong>Match suit or rank.</strong> On your turn, play a card that matches either the suit or the rank of the top discard pile card, or trigger a special ability.</li>
            <li><strong>Deploy special abilities.</strong> Jump a player, ask a question, reverse direction, stack a penalty, or declare a new suit with an Ace.</li>
            <li><strong>No valid play? Draw a card.</strong> If you can't match the suit, rank, or respond to an active effect, you must draw one card from the pile.</li>
        </ol>
    </section>

    <section id="winning" data-help-section>
        <h2>Winning the game</h2>
        <h3>The "Kadi!" call</h3>
        <p>The moment a player is down to one card, or one move from winning, the server automatically announces <strong>"Kadi!"</strong>
            to the whole table. This gives every opponent one last chance to disrupt or counter the impending victory before the final card lands.</p>
        <h3>Finishing restrictions</h3>
        <p>Your final card <strong>must</strong> be a standard Play Card (4, 5, 6, 7, 9 or 10). Finish on any of these instead and you don't win:
            2, 3, Joker, 8, Queen, King, Jack, Ace.</p>
        <h3>Going Kadiless</h3>
        <p>Land on a restricted card as your last move and you become <strong>Kadiless</strong>: you don't win, and you also lose the ability to
            defend anyone else from winning. If another player is already Kadiless, nobody at the table can win until the board clears.</p>
        <div class="nt-panel">
            <p>To secure the win, your winning card <strong>and</strong> the board state immediately before it must be entirely clear of restricted
                cards. A Joker can't be sitting on top of the discard pile the moment before your last move. Play clean, play smart, and empty your hand.</p>
        </div>
    </section>

    <section id="strategy" data-help-section>
        <h2>The essence of Kadi</h2>
        <p><strong>Not a game of matching cards. A battle of strategy, timing and psychology.</strong></p>
        <p>The strongest players study their opponents: the cards they hesitate to play, the suits they avoid, the chains they're quietly
            building. Every move should have a purpose: know when to strike, when to hold back, and when to force a rival into an uncomfortable decision.</p>
        <p>Outthink. Outplay. Outfox.</p>
    </section>

    <section aria-labelledby="ready">
        <h2 id="ready">You know the rules. Now prove it.</h2>
        <p>Take your new knowledge to the table and challenge real opponents in competitive Kadi.</p>
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
