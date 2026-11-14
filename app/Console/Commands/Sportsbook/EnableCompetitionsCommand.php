<?php

namespace App\Console\Commands\Sportsbook;

use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Sport;
use App\Sportsbook\Support\SportProfile;
use Database\Seeders\Sportsbook\CompetitionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sportsbook:enable-competitions
    {competition?* : Feed competition keys to switch on, e.g. soccer_usa_mls}
    {--defaults : Every competition in config sportsbook.odds_api.default_competitions}
    {--dry-run : Show what would change without changing anything}')]
#[Description('Switch syncing on for the given competitions, after the existing ones (never touches any other)')]
class EnableCompetitionsCommand extends Command
{
    public function handle(): int
    {
        /** @var list<string> $keys */
        $keys = $this->option('defaults')
            ? (array) config('sportsbook.odds_api.default_competitions')
            : array_values(array_filter((array) $this->argument('competition')));

        if ($keys === []) {
            $this->error('Give competition keys (e.g. soccer_usa_mls) or --defaults.');

            return self::FAILURE;
        }

        $nextOrder = (int) Competition::max('sort_order');
        $rows = [];

        foreach (array_unique($keys) as $key) {
            if (SportProfile::forCompetitionKey($key) === null) {
                $rows[] = [$key, 'skipped: no configured sport has this prefix'];

                continue;
            }

            $competition = Competition::firstWhere('odds_api_key', $key);

            if ($competition?->is_synced) {
                $rows[] = [$key, 'already on'];

                continue;
            }

            $rows[] = [$key, ($competition === null ? 'created and switched on' : 'switched on').' (order '.++$nextOrder.')'.($competition !== null && ! $competition->is_active_in_feed ? ', waiting for its season' : '')];

            if ($this->option('dry-run')) {
                continue;
            }

            // Logged by the model's activity log (log `odds`), like a switch from GMS.
            $competition === null
                ? $this->create($key, $nextOrder)
                : $competition->update(['is_synced' => true, 'sort_order' => $nextOrder]);
        }

        $this->table(['Competition', $this->option('dry-run') ? 'Would be' : 'Result'], $rows);

        return self::SUCCESS;
    }

    /** A competition the feed has not listed yet (out of season): synced as soon as the feed lists it. */
    private function create(string $key, int $sortOrder): void
    {
        /** @var SportProfile $profile */
        $profile = SportProfile::forCompetitionKey($key);
        $sport = Sport::firstOrCreate(['slug' => $profile->slug], ['name' => $profile->name, 'sort_order' => $profile->sortOrder]);

        Competition::create([
            'sport_id' => $sport->id,
            'odds_api_key' => $key,
            'name' => CompetitionSeeder::NAMES[$key] ?? $key,
            // The next fixture sync marks it inactive if the feed does not list it yet, and active once it does.
            'is_active_in_feed' => false,
            'is_synced' => true,
            'sort_order' => $sortOrder,
        ]);
    }
}
