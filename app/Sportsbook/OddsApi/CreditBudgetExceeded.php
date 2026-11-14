<?php

namespace App\Sportsbook\OddsApi;

use RuntimeException;

class CreditBudgetExceeded extends RuntimeException
{
    public static function monthlyCap(int $estimatedCost, int $spentThisMonth, int $cap): self
    {
        return new self("Refusing a {$estimatedCost}-credit Odds API call: {$spentThisMonth} of the {$cap}-credit monthly cap is already spent.");
    }

    public static function allowance(int $estimatedCost, OddsApiPurpose $purpose, int $spentOnPurpose, int $allowance): self
    {
        return new self("Refusing a {$estimatedCost}-credit Odds API call: {$spentOnPurpose} of the {$allowance}-credit monthly {$purpose->value} allowance is already spent.");
    }

    public static function reserve(int $estimatedCost, int $remaining, int $reserve): self
    {
        return new self("Refusing a {$estimatedCost}-credit Odds API call: only {$remaining} credits remain and {$reserve} are held in reserve.");
    }
}
