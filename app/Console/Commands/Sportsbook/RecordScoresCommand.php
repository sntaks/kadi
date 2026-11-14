<?php

namespace App\Console\Commands\Sportsbook;

use App\Sportsbook\OddsApi\CreditBudget;
use App\Sportsbook\OddsApi\LiveOddsApi;
use App\Sportsbook\OddsApi\OddsApiPurpose;
use App\Sportsbook\OddsApi\ReplayOddsApi;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;

#[Signature('sportsbook:record-scores
    {competition : The competition key, e.g. soccer_epl}
    {--days=3 : Include games completed in this many days (1 to 3)}
    {--live : Record the games in play now (no --days, 1 credit) for the in-play replay; run it while matches are on, with record-odds-api}
    {--force : Skip the credit confirmation}')]
#[Description('Record a live Odds API scores response for the replay driver and tests (spends 2 credits)')]
class RecordScoresCommand extends Command
{
    public function handle(CreditBudget $budget): int
    {
        if (blank(config('services.odds_api.key'))) {
            $this->error('ODDS_API_KEY is not set.');

            return self::FAILURE;
        }

        $competition = $this->argument('competition');
        $daysOption = $this->option('days');

        if ($competition === '') {
            $this->error('Give a competition key, e.g. soccer_epl.');

            return self::FAILURE;
        }

        $live = (bool) $this->option('live');
        $days = $live ? 0 : max(1, min(3, is_numeric($daysOption) ? (int) $daysOption : 3));

        if (! $this->option('force') && ! confirm($live ? "Spend 1 Odds API credit on {$competition} live scores?" : "Spend 2 Odds API credits on {$competition} scores for the last {$days} days?", default: false)) {
            $this->warn('Nothing recorded.');

            return self::FAILURE;
        }

        $scores = LiveOddsApi::fromConfig($budget)->scores($competition, $days, OddsApiPurpose::Recording);
        $path = (string) config('sportsbook.odds_api.recordings_path');
        File::ensureDirectoryExists($path);

        File::put(
            ReplayOddsApi::recordingFile($path, $live ? 'scores-live' : 'scores', $competition),
            json_encode([
                'recorded_at' => now('UTC')->toIso8601ZuluString(),
                'endpoint' => $live ? 'scores-live' : 'scores',
                'sport_key' => $competition,
                'data' => $scores,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
        );

        $completed = count(array_filter($scores, fn (array $game): bool => $game['completed']));
        $this->info('Recorded '.count($scores)." games ({$completed} completed). ".$budget->spentThisMonth().' credits spent this month, '.($budget->remaining() ?? 'unknown').' remaining.');

        return self::SUCCESS;
    }
}
