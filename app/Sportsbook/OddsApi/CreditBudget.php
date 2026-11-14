<?php

namespace App\Sportsbook\OddsApi;

use App\Sportsbook\Models\OddsApiCall;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps paid Odds API usage inside the monthly cap, inside each purpose's own monthly allowance and above the
 * reserve, using the call ledger.
 */
class CreditBudget
{
    public const LOCK = 'sportsbook.odds-api.paid-call';

    /**
     * @param  array<string, int>  $allowances  Purpose value => credits a month. A purpose without one only has the cap.
     */
    public function __construct(
        private readonly int $monthlyCap,
        private readonly int $reserve,
        private readonly array $allowances = [],
    ) {}

    public static function fromConfig(): self
    {
        /** @var array<string, int|string|null> $allowances */
        $allowances = (array) config('sportsbook.odds_api.allowances', []);

        return new self(
            monthlyCap: (int) config('sportsbook.odds_api.monthly_credit_cap'),
            reserve: (int) config('sportsbook.odds_api.credit_reserve'),
            allowances: array_map(intval(...), $allowances),
        );
    }

    /**
     * @throws CreditBudgetExceeded
     */
    public function ensureCanSpend(int $estimatedCost, ?OddsApiPurpose $purpose = null): void
    {
        $spentThisMonth = $this->spentThisMonth();

        if ($spentThisMonth + $estimatedCost > $this->monthlyCap) {
            throw CreditBudgetExceeded::monthlyCap($estimatedCost, $spentThisMonth, $this->monthlyCap);
        }

        $allowance = $this->allowance($purpose);

        if ($purpose !== null && $allowance !== null) {
            $spentOnPurpose = $this->spentThisMonth($purpose);

            if ($spentOnPurpose + $estimatedCost > $allowance) {
                throw CreditBudgetExceeded::allowance($estimatedCost, $purpose, $spentOnPurpose, $allowance);
            }
        }

        $remaining = $this->remaining();

        if ($remaining !== null && $remaining - $estimatedCost < $this->reserve) {
            throw CreditBudgetExceeded::reserve($estimatedCost, $remaining, $this->reserve);
        }
    }

    /**
     * Make one paid call inside the budget. Paid calls run one at a time (a cache lock held through the check, the
     * request and recording its cost), so parallel queue workers can never all pass the check against the same
     * spend figure and together go past the cap, an allowance or into the reserve.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     *
     * @throws CreditBudgetExceeded
     */
    public function spend(int $estimatedCost, callable $call, ?OddsApiPurpose $purpose = null): mixed
    {
        // Longer than a paid call's own timeout (5 s connect + 20 s), so the lock outlives the request it guards.
        return Cache::lock(self::LOCK, 40)->block(60, function () use ($estimatedCost, $call, $purpose) {
            $this->ensureCanSpend($estimatedCost, $purpose);

            return $call();
        });
    }

    /** Credits spent since the start of this (UTC) month, in total or on one purpose. */
    public function spentThisMonth(?OddsApiPurpose $purpose = null): int
    {
        return (int) OddsApiCall::query()
            ->where('created_at', '>=', now('UTC')->startOfMonth())
            ->when($purpose !== null, fn ($query) => $query->where('purpose', $purpose?->value))
            ->sum('credits_spent');
    }

    /** A purpose's monthly allowance, or null when it has none (only the cap applies). */
    public function allowance(?OddsApiPurpose $purpose): ?int
    {
        return $purpose !== null && array_key_exists($purpose->value, $this->allowances)
            ? $this->allowances[$purpose->value]
            : null;
    }

    public function monthlyCap(): int
    {
        return $this->monthlyCap;
    }

    public function reserve(): int
    {
        return $this->reserve;
    }

    /**
     * This month's spend per purpose, with each purpose's allowance (null: none of its own), in one query.
     *
     * @return array<string, array{spent: int, allowance: int|null}>
     */
    public function usageThisMonth(): array
    {
        $spent = OddsApiCall::query()
            ->where('created_at', '>=', now('UTC')->startOfMonth())
            ->whereNotNull('purpose')
            ->toBase()
            ->selectRaw('purpose, sum(credits_spent) as spent')
            ->groupBy('purpose')
            ->pluck('spent', 'purpose');

        $usage = [];

        foreach (OddsApiPurpose::cases() as $purpose) {
            $usage[$purpose->value] = [
                'spent' => (int) ($spent[$purpose->value] ?? 0),
                'allowance' => $this->allowance($purpose),
            ];
        }

        return $usage;
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
