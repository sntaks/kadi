<?php

namespace App\Console\Commands\Sportsbook;

use App\Sportsbook\OddsApi\CreditBudget;
use App\Sportsbook\OddsApi\LiveOddsApi;
use App\Sportsbook\OddsApi\OddsApiPurpose;
use App\Sportsbook\OddsApi\OddsApiSource;
use App\Sportsbook\OddsApi\ReplayOddsApi;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;

#[Signature('sportsbook:record-odds-api
    {--markets=h2h,totals : Comma-separated market keys to record}
    {--competition=* : Competition keys to record (defaults to the default competitions)}
    {--force : Skip the credit confirmation}')]
#[Description('Record live Odds API responses for the replay driver (spends credits for odds)')]
class RecordOddsApiCommand extends Command
{
    public function handle(CreditBudget $budget): int
    {
        if (blank(config('services.odds_api.key'))) {
            $this->error('ODDS_API_KEY is not set.');

            return self::FAILURE;
        }

        /** @var list<string> $competitions */
        $competitions = $this->option('competition') ?: config('sportsbook.odds_api.default_competitions');
        $marketsOption = $this->option('markets');
        $markets = is_string($marketsOption) ? array_values(array_filter(array_map('trim', explode(',', $marketsOption)))) : [];
        $estimatedCredits = count($competitions) * count($markets) * OddsApiSource::fromConfig()->creditsPerMarket();

        $this->line('Recording sports, fixtures and ['.implode(', ', $markets).'] odds for: '.implode(', ', $competitions));
        $this->line("Odds cost about {$estimatedCredits} credits; sports and fixtures are free.");

        if (! $this->option('force') && ! confirm("Spend about {$estimatedCredits} Odds API credits?", default: false)) {
            $this->warn('Nothing recorded.');

            return self::FAILURE;
        }

        $live = LiveOddsApi::fromConfig($budget);
        $path = (string) config('sportsbook.odds_api.recordings_path');
        File::ensureDirectoryExists($path);

        $this->write($path, 'sports', null, $live->sports());

        foreach ($competitions as $competition) {
            $this->write($path, 'events', $competition, $live->events($competition));
            $this->write($path, 'odds', $competition, $live->odds($competition, $markets, OddsApiPurpose::Recording));
            $this->line("Recorded {$competition}.");
        }

        $this->info('Done. '.$budget->spentThisMonth().' credits spent this month, '.($budget->remaining() ?? 'unknown').' remaining.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, mixed>  $data
     */
    private function write(string $path, string $endpoint, ?string $sportKey, array $data): void
    {
        File::put(
            ReplayOddsApi::recordingFile($path, $endpoint, $sportKey),
            json_encode([
                'recorded_at' => now('UTC')->toIso8601ZuluString(),
                'endpoint' => $endpoint,
                'sport_key' => $sportKey,
                'data' => $data,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
        );
    }
}
