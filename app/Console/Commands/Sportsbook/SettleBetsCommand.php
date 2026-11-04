<?php

namespace App\Console\Commands\Sportsbook;

use App\Sportsbook\Betting\Jackpot\VoidPostponedJackpotMatches;
use App\Sportsbook\Betting\Settlement\FetchResults;
use App\Sportsbook\Betting\Settlement\VoidPostponedLegs;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sportsbook:settle')]
#[Description('Void bet legs and jackpot matches on postponed matches and settle finished matches from the scores feed (credits only when bets are waiting)')]
class SettleBetsCommand extends Command
{
    public function handle(VoidPostponedLegs $voidPostponedLegs, VoidPostponedJackpotMatches $voidPostponedJackpotMatches, FetchResults $fetchResults): int
    {
        $voided = $voidPostponedLegs->handle();
        $voidedJackpotMatches = $voidPostponedJackpotMatches->handle();
        $fetched = $fetchResults->handle();

        $this->info("Voided {$voided} postponed legs and {$voidedJackpotMatches} jackpot matches. Fetched scores for {$fetched['competitions']} competitions and recorded {$fetched['results']} results.");

        return self::SUCCESS;
    }
}
