<?php

namespace App\Sportsbook\OddsApi;

use RuntimeException;

class CreditBudgetExceeded extends RuntimeException
{
    public static function monthlyCap(int $estimatedCost, int $spentThisMonth, int $cap): self
    {
        return new self("Refusing a {$estimatedCost}-credit Odds API call: {$spentThisMonth} of the {$cap}-credit monthly cap is already spent.");
    }

    public static function reserve(int $estimatedCost, int $remaining, int $reserve): self
    {
        return new self("Refusing a {$estimatedCost}-credit Odds API call: only {$remaining} credits remain and {$reserve} are held in reserve.");
    }
}
