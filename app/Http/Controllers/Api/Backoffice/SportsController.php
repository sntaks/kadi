<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Sportsbook\Betting\Exposure;
use App\Sportsbook\Betting\Jackpot\CancelJackpot;
use App\Sportsbook\Betting\Jackpot\DraftJackpot;
use App\Sportsbook\Betting\Jackpot\PublishJackpot;
use App\Sportsbook\Betting\Jackpot\SettleJackpot;
use App\Sportsbook\Betting\Settlement\CancelBet;
use App\Sportsbook\Betting\Settlement\RecordResult;
use App\Sportsbook\Betting\Settlement\VoidLeg;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\BetLeg;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\FixtureResult;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\WagerOp;
use App\Sportsbook\OddsApi\CreditBudget;
use App\Sportsbook\Support\StaffActor;
use App\Sportsbook\Trading\MarketCannotBeReopened;
use App\Sportsbook\Trading\OverrideSelectionPrices;
use App\Sportsbook\Trading\ReopenMarket;
use App\Sportsbook\Trading\SuspendMarket;
use App\Sportsbook\Wagers\NeedsOperator;
use App\Sportsbook\Wagers\WagerOps;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * /api/v1/backoffice/sports: what GMS staff do to the sportsbook (plan §5.5, docs/backoffice-api.md). Every write
 * goes through the same actions as everything else, so settlements still reach the Kadi API through the
 * sb_wager_ops outbox in the same transaction, and the staff member is named in the activity log. GMS reads lists
 * straight from this database (read-only user); only what needs this app's rules is served here.
 */
class SportsController extends Controller
{
    // ── Reads ────────────────────────────────────────────────

    /** Worst-case payouts by match and pick, for the open bets on matches still to play. */
    public function exposure(Request $request, Exposure $exposure): JsonResponse
    {
        $limit = max(1, min(500, (int) $request->query('limit', 100)));

        return $this->ok(['matches' => $exposure->byMatch($limit)]);
    }

    /** Each prize tier with its winners so far and what each would get. */
    public function jackpotTiers(Jackpot $jackpot): JsonResponse
    {
        return $this->ok(['jackpot' => $jackpot->id, 'status' => $jackpot->status->value, 'tiers' => SettleJackpot::tiers($jackpot)]);
    }

    /** Odds API credits spent this month and what is left (the paid feed). */
    public function oddsApiCredits(CreditBudget $budget): JsonResponse
    {
        return $this->ok([
            'spent_this_month' => $budget->spentThisMonth(),
            'remaining' => $budget->remaining(),
            'monthly_cap' => (int) config('sportsbook.odds_api.monthly_credit_cap'),
            'reserve' => (int) config('sportsbook.odds_api.credit_reserve'),
        ]);
    }

    /**
     * One Nairobi day of sports wagers as this site recorded them, by product, to compare with the Kadi API's own
     * figures; plus what needs an operator now (failed or overdue ops, placements still unknown). Amounts in cents.
     */
    public function reconciliation(Request $request, NeedsOperator $needs): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $tz = (string) config('sportsbook.timezone');
        $day = CarbonImmutable::parse($data['date'] ?? now($tz)->toDateString(), $tz);
        [$from, $to] = [$day->startOfDay()->utc(), $day->endOfDay()->utc()];

        $bets = Bet::query()->whereBetween('placed_at', [$from, $to]);
        $settledBets = Bet::query()->whereBetween('settled_at', [$from, $to]);
        $entries = JackpotEntry::query()->whereBetween('placed_at', [$from, $to]);
        $settledEntries = JackpotEntry::query()->whereBetween('settled_at', [$from, $to]);

        return $this->ok([
            'date' => $day->toDateString(),
            'timezone' => $tz,
            'products' => [
                (string) config('sportsbook.wagers.products.bet') => [
                    'placed' => $this->countsByStatus($bets->clone()),
                    'cash_stakes' => (int) $bets->clone()->whereNull('free_bet_id')->whereNotIn('status', [BetStatus::Pending, BetStatus::Rejected])->sum('stake'),
                    'free_bet_stakes' => (int) $bets->clone()->whereNotNull('free_bet_id')->whereNotIn('status', [BetStatus::Pending, BetStatus::Rejected])->sum('stake'),
                    'settled' => $this->countsByStatus($settledBets->clone()),
                    'payouts' => (int) $settledBets->clone()->whereIn('status', [BetStatus::Won])->sum('payout'),
                    'refunds' => (int) $settledBets->clone()->whereIn('status', [BetStatus::Void, BetStatus::Cancelled])->sum('payout'),
                    'tax' => (int) $settledBets->clone()->sum('tax'),
                ],
                (string) config('sportsbook.wagers.products.jackpot_entry') => [
                    'placed' => $this->countsByStatus($entries->clone()),
                    'stakes' => (int) $entries->clone()->whereNotIn('status', [JackpotEntryStatus::Pending, JackpotEntryStatus::Rejected])->sum('stake'),
                    'settled' => $this->countsByStatus($settledEntries->clone()),
                    'prizes' => (int) $settledEntries->clone()->where('status', JackpotEntryStatus::Won)->sum('prize'),
                    'refunds' => (int) $settledEntries->clone()->where('status', JackpotEntryStatus::Refunded)->sum('prize'),
                    'tax' => (int) $settledEntries->clone()->sum('tax'),
                ],
            ],
            'needs_operator' => [
                'ops' => $needs->ops()->map(fn (WagerOp $op): array => [
                    'id' => $op->id, 'product' => $op->product, 'wager_reference' => $op->wager_reference, 'subject_type' => $op->subject_type,
                    'subject_id' => $op->subject_id, 'operation' => $op->operation, 'version' => $op->version, 'status' => $op->status,
                    'attempts' => $op->attempts, 'last_status' => $op->last_status, 'last_error' => $op->last_error,
                    'created_at' => $op->created_at?->toIso8601String(),
                ])->all(),
                'pending_bets' => $needs->pendingBets()->pluck('reference')->all(),
                'pending_jackpot_entries' => $needs->pendingJackpotEntries()->pluck('reference')->all(),
            ],
        ]);
    }

    // ── Trading ──────────────────────────────────────────────

    public function suspendMarket(Request $request, Market $market, SuspendMarket $suspend): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $suspend->handle($market, $this->staff($request), $data['reason']);

        return $this->ok(['market' => $market->id, 'status' => $market->fresh()?->status->value]);
    }

    public function reopenMarket(Request $request, Market $market, ReopenMarket $reopen): JsonResponse
    {
        try {
            $reopen->handle($market, $this->staff($request));
        } catch (MarketCannotBeReopened $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'market_cannot_be_reopened'], 409);
        }

        return $this->ok(['market' => $market->id, 'status' => $market->fresh()?->status->value]);
    }

    /** Set or clear price overrides: `prices` maps selection id => price, or null to clear it. */
    public function overridePrices(Request $request, Market $market, OverrideSelectionPrices $override): JsonResponse
    {
        $data = $request->validate([
            'prices' => ['required', 'array', 'min:1'],
            'prices.*' => ['nullable', 'numeric'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $unknown = array_diff(array_map('intval', array_keys($data['prices'])), $market->selections()->pluck('id')->all());

        if ($unknown !== []) {
            throw ValidationException::withMessages(['prices' => 'Every price must be for a selection of this market.']);
        }

        try {
            $override->handle(
                $market->load('selections', 'fixture'),
                $this->staff($request),
                collect($data['prices'])->mapWithKeys(fn ($price, $id) => [(int) $id => $price === null ? null : (string) $price])->all(),
                $data['reason'],
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['prices' => $e->getMessage()]);
        }

        return $this->ok(['market' => $market->id, 'selections' => $market->selections()->get()->map(fn ($s) => [
            'id' => $s->id, 'name' => $s->name, 'feed_price' => $s->feed_price, 'override_price' => $s->override_price, 'price' => $s->price(),
        ])->all()]);
    }

    /** Record or correct a fixture's result; bets and jackpots settle (or re-settle) on it. */
    public function recordResult(Request $request, Fixture $fixture, RecordResult $record): JsonResponse
    {
        $score = ['nullable', 'integer', 'min:0', 'max:99'];
        $data = $request->validate([
            'is_void' => ['sometimes', 'boolean'],
            'home_score' => [Rule::requiredIf(! $request->boolean('is_void')), ...$score],
            'away_score' => [Rule::requiredIf(! $request->boolean('is_void')), ...$score],
            'half_time_home_score' => [...$score, 'required_with:half_time_away_score'],
            'half_time_away_score' => [...$score, 'required_with:half_time_home_score'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $counts = $record->handle($fixture, $data, FixtureResult::SOURCE_ADMIN, $this->staff($request));

        return $this->ok(['fixture' => $fixture->id, 'legs_decided' => $counts['legs'], 'bets_settled' => $counts['bets']]);
    }

    public function updateCompetition(Request $request, Competition $competition): JsonResponse
    {
        $data = $request->validate([
            'is_synced' => ['sometimes', 'boolean'],
            'auto_settle' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ]);
        $staff = $this->staff($request);

        $before = $competition->only(array_keys($data));
        $competition->update($data);

        activity('odds')
            ->performedOn($competition)
            ->event('competition-updated')
            ->withProperties(StaffActor::with($staff, ['competition' => $competition->name, 'from' => $before, 'to' => $data]))
            ->log('Competition updated');

        return $this->ok(['competition' => $competition->only(['id', 'name', 'slug', 'is_synced', 'auto_settle', 'sort_order'])]);
    }

    // ── Bets ─────────────────────────────────────────────────

    public function voidLeg(Request $request, BetLeg $leg, VoidLeg $void): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $void->handle($leg, $data['reason'], $this->staff($request));

        return $this->ok(['leg' => $leg->id, 'bet' => $this->betState($leg->bet()->firstOrFail())]);
    }

    public function cancelBet(Request $request, Bet $bet, CancelBet $cancel): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $bet = $cancel->handle($bet, $this->staff($request), $data['reason']);

        return $this->ok(['bet' => $this->betState($bet)]);
    }

    public function retryOp(Request $request, WagerOp $op, WagerOps $ops): JsonResponse
    {
        $staff = $this->staff($request);

        if ($op->status !== WagerOp::FAILED) {
            return response()->json(['message' => 'Only a failed op can be put back in line.', 'code' => 'op_not_failed'], 409);
        }

        $ops->retry($op);

        activity('settlement')
            ->event('wager-op-retried')
            ->withProperties(StaffActor::with($staff, ['op' => $op->id, 'wager_reference' => $op->wager_reference, 'operation' => $op->operation, 'version' => $op->version]))
            ->log('Wager op put back in line');

        return $this->ok(['op' => $op->id, 'status' => $op->fresh()?->status]);
    }

    // ── Jackpots ─────────────────────────────────────────────

    public function draftJackpot(Request $request, DraftJackpot $draft, ?Jackpot $jackpot = null): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'entry_price' => ['required', 'integer', 'min:100'],
            'void_limit' => ['required', 'integer', 'min:0', 'max:20'],
            'prize_tiers' => ['required', 'array', 'min:1', 'max:5'],
            'prize_tiers.*.correct' => ['required', 'integer', 'min:1', 'max:30'],
            'prize_tiers.*.pool' => ['required', 'integer', 'min:0'],
            'fixture_ids' => ['required', 'array', 'min:1', 'max:30'],
            'fixture_ids.*' => ['required', 'integer'],
        ]);

        $jackpot = $draft->handle($data, $jackpot, $this->staff($request));

        return $this->ok(['jackpot' => $this->jackpotState($jackpot)], $request->isMethod('post') ? 201 : 200);
    }

    public function publishJackpot(Request $request, Jackpot $jackpot, PublishJackpot $publish): JsonResponse
    {
        return $this->ok(['jackpot' => $this->jackpotState($publish->handle($jackpot, $this->staff($request)))]);
    }

    public function cancelJackpot(Request $request, Jackpot $jackpot, CancelJackpot $cancel): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return $this->ok(['jackpot' => $this->jackpotState($cancel->handle($jackpot, $data['reason'], $this->staff($request)))]);
    }

    // ── Helpers ──────────────────────────────────────────────

    /** The GMS staff member acting, from the signed body. */
    private function staff(Request $request): StaffActor
    {
        $data = $request->validate([
            'staff' => ['required', 'array'],
            'staff.id' => ['required', 'string', 'max:50'],
            'staff.name' => ['required', 'string', 'max:100'],
            'staff.email' => ['nullable', 'email', 'max:150'],
        ]);

        return new StaffActor($data['staff']['id'], $data['staff']['name'], $data['staff']['email'] ?? null);
    }

    /**
     * @param  Builder<Bet>|Builder<JackpotEntry>  $query
     * @return array<string, int>
     */
    private function countsByStatus($query): array
    {
        return $query->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all();
    }

    /** @return array<string, mixed> */
    private function betState(Bet $bet): array
    {
        return $bet->fresh()?->only(['id', 'reference', 'wager_reference', 'status', 'stake', 'payout', 'bonus', 'tax', 'settlement_version']) ?? [];
    }

    /** @return array<string, mixed> */
    private function jackpotState(Jackpot $jackpot): array
    {
        $jackpot = $jackpot->fresh() ?? $jackpot;

        return $jackpot->only(['id', 'name', 'status', 'entry_price', 'void_limit', 'prize_tiers', 'settlement_version', 'published_at', 'cancel_reason'])
            + ['matches' => $jackpot->jackpotFixtures()->count()];
    }

    /** @param  array<string, mixed>  $data */
    private function ok(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $data], $status);
    }
}
