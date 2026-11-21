<?php

namespace App\Sportsbook\Support;

use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\BetLeg;

/**
 * How a sports bet reads to the player (sports-bet-card, the bet slip's My bets): one status, one line that says why
 * it won or lost, and a status per pick. It follows the usual sportsbook pattern: a multibet is lost as soon as one
 * pick loses, that pick is marked, and picks that no longer matter say so instead of looking live.
 *
 * Display only. Settlement (SettleBet) decides the money; a bet whose lost pick is not settled yet still shows as
 * lost, because no later result can change that.
 */
final readonly class BetOutcome
{
    /** won | lost | open | void: the colour of the bet's badge and border. */
    public string $tone;

    public string $label;

    /** Why the bet stands where it does, in one sentence. */
    public string $reason;

    public int $total;

    public int $won;

    public int $lost;

    public int $void;

    public int $open;

    public function __construct(public Bet $bet)
    {
        $legs = $bet->legs;
        $this->total = $legs->count();
        $this->won = $legs->where('status', BetLegStatus::Won)->count();
        $this->lost = $legs->where('status', BetLegStatus::Lost)->count();
        $this->void = $legs->where('status', BetLegStatus::Void)->count();
        $this->open = $legs->where('status', BetLegStatus::Open)->count();

        $decidedLost = $bet->status === BetStatus::Open && $this->lost > 0;

        $this->tone = match (true) {
            $bet->status === BetStatus::Won => 'won',
            $bet->status === BetStatus::Lost, $decidedLost => 'lost',
            in_array($bet->status, [BetStatus::Open, BetStatus::Pending], true) => 'open',
            default => 'void',
        };
        $this->label = $decidedLost ? BetStatus::Lost->label() : $bet->status->label();
        $this->reason = $this->reason();
    }

    public function isMulti(): bool
    {
        return $this->total > 1;
    }

    /** A pick still waiting for its match on a bet that is already lost: it can no longer change anything. */
    public function legIsMoot(BetLeg $leg): bool
    {
        return $leg->status === BetLegStatus::Open && $this->tone === 'lost';
    }

    /** won | lost | void | live | open | moot */
    public function legTone(BetLeg $leg, bool $live): string
    {
        return match (true) {
            $leg->status === BetLegStatus::Won => 'won',
            $leg->status === BetLegStatus::Lost => 'lost',
            $leg->status === BetLegStatus::Void => 'void',
            $this->legIsMoot($leg) => 'moot',
            $live => 'live',
            default => 'open',
        };
    }

    public function legLabel(BetLeg $leg, bool $live): string
    {
        return match ($this->legTone($leg, $live)) {
            'won' => __('Won'),
            'lost' => __('Lost'),
            'void' => __('Void · odds 1.00'),
            'moot' => __('Not needed'),
            'live' => __('Live'),
            default => __('Pending'),
        };
    }

    private function reason(): string
    {
        $bet = $this->bet;

        if ($this->tone === 'lost') {
            if (! $this->isMulti()) {
                return __('Your pick lost.');
            }

            /** @var BetLeg $leg */
            $leg = $bet->legs->firstWhere('status', BetLegStatus::Lost);

            return $this->lost === 1
                ? __('Lost on :match (:pick). In a multibet every pick must win.', ['match' => $leg->fixture_name, 'pick' => $leg->selection_name])
                : __(':lost of :total picks lost. In a multibet every pick must win.', ['lost' => $this->lost, 'total' => $this->total]);
        }

        $voids = $this->void > 0 ? ' '.trans_choice(':count void pick counted at odds 1.00.|:count void picks counted at odds 1.00.', $this->void) : '';

        return match ($bet->status) {
            BetStatus::Won => ($this->isMulti() ? __('All :count picks won.', ['count' => $this->won]) : __('Your pick won.')).$voids,
            BetStatus::Open => $this->isMulti()
                ? __(':won of :total won, :open to play.', ['won' => $this->won, 'total' => $this->total, 'open' => $this->open]).$voids
                : __('Waiting for the result.'),
            BetStatus::Pending => __('Checking your bet with the wallet.'),
            BetStatus::Void => __('Every pick was void, so your stake came back.'),
            BetStatus::Cancelled => __('Cancelled. Your stake came back.'),
            BetStatus::Rejected => __('Not placed. Nothing was taken from your wallet.'),
            default => '',
        };
    }
}
