<?php

namespace App\Sportsbook\OddsApi;

use App\Sportsbook\Models\OddsApiCall;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps paid Odds API usage inside the monthly cap and above the reserve, using the call ledger.
 */
class CreditBudget
{
    public const LOCK = 'sportsbook.odds-api.paid-call';

    public function __construct(
        private readonly int $monthlyCap,
        private readonly int $reserve,
    ) {}

    /**
     * @throws CreditBudgetExceeded
     */
    public function ensureCanSpend(int $estimatedCost): void
    {
        $spentThisMonth = $this->spentThisMonth();

        if ($spentThisMonth + $estimatedCost > $this->monthlyCap) {
            throw CreditBudgetExceeded::monthlyCap($estimatedCost, $spentThisMonth, $this->monthlyCap);
        }

        $remaining = $this->remaining();

        if ($remaining !== null && $remaining - $estimatedCost < $this->reserve) {
            throw CreditBudgetExceeded::reserve($estimatedCost, $remaining, $this->reserve);
        }
    }

    /**
     * Make one paid call inside the budget. Paid calls run one at a time (a cache lock held through the check, the
     * request and recording its cost), so parallel queue workers can never all pass the check against the same
     * spend figure and together go past the cap or into the reserve.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     *
     * @throws CreditBudgetExceeded
     */
    public function spend(int $estimatedCost, callable $call): mixed
    {
        // Longer than a paid call's own timeout (5 s connect + 20 s), so the lock outlives the request it guards.
        return Cache::lock(self::LOCK, 40)->block(60, function () use ($estimatedCost, $call) {
            $this->ensureCanSpend($estimatedCost);

            return $call();
        });
    }

    public function spentThisMonth(): int
    {
        return (int) OddsApiCall::query()
            ->where('created_at', '>=', now('UTC')->startOfMonth())
            ->sum('credits_spent');
    }

    /**
     * Credits left on the plan as of the most recent call that reported them, or null when none has.
     */
    public function remaining(): ?int
    {
        return OddsApiCall::query()
            ->whereNotNull('credits_remaining')
            ->latest('created_at')
            ->latest('id')
            ->value('credits_remaining');
    }
}
