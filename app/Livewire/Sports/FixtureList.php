<?php

namespace App\Livewire\Sports;

use App\Sportsbook\Catalogue\FixtureListing;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The football list (plan §2): tabs (Highlights, Today, Tomorrow, This week), a market switcher, and rows grouped by
 * day and competition. On /sports and a competition page it grows as the player scrolls; on the landing it shows the
 * first page and links to the rest. Counts are the real ones.
 *
 * @property-read ?Competition $competition
 * @property-read Collection<int, Fixture> $fixtures
 * @property-read int $total
 */
class FixtureList extends Component
{
    private const BATCH = 20;

    public string $tab = 'highlights';

    public string $marketKey = 'h2h';

    #[Locked]
    public ?int $competitionId = null;

    /** The landing's first page only, with a link to /sports. */
    #[Locked]
    public bool $preview = false;

    #[Locked]
    public int $shown = self::BATCH;

    /** The list's heading: "Football" on the landing, "Matches" under a page's own title. */
    #[Locked]
    public string $heading = 'Football';

    public function mount(?int $competitionId = null, bool $preview = false, string $tab = 'highlights', string $heading = 'Football'): void
    {
        $this->heading = $heading;
        $this->tab = array_key_exists($tab, FixtureListing::TABS) ? $tab : 'highlights';
        $this->competitionId = $competitionId;
        $this->preview = $preview;
        $this->shown = $preview ? 10 : self::BATCH;
    }

    public function updated(string $property): void
    {
        $this->tab = array_key_exists($this->tab, FixtureListing::TABS) ? $this->tab : 'highlights';
        $this->marketKey = array_key_exists($this->marketKey, FixtureListing::LIST_MARKETS) ? $this->marketKey : 'h2h';

        if ($property === 'tab' && ! $this->preview) {
            $this->shown = self::BATCH;
        }

        unset($this->fixtures, $this->total);
    }

    public function showMore(): void
    {
        if (! $this->preview) {
            $this->shown = min($this->shown + self::BATCH, $this->total);
            unset($this->fixtures);
        }
    }

    #[Computed]
    public function competition(): ?Competition
    {
        return $this->competitionId === null ? null : Competition::query()->find($this->competitionId);
    }

    /**
     * @return Collection<int, Fixture>
     */
    #[Computed]
    public function fixtures(): Collection
    {
        return app(FixtureListing::class)->forTab($this->tab, $this->marketKey, $this->competition, $this->shown);
    }

    #[Computed]
    public function total(): int
    {
        return app(FixtureListing::class)->count($this->tab, $this->competition);
    }

    public function render(): View
    {
        $tz = (string) config('sportsbook.timezone');

        return view('livewire.sports.fixture-list', [
            'groups' => $this->fixtures->groupBy(fn (Fixture $fixture): string => $fixture->kicks_off_at->timezone($tz)->toDateString().'|'.$fixture->competition->name),
            'columns' => in_array($this->marketKey, ['h2h', 'double_chance'], true) ? 3 : 2,
            'labels' => match ($this->marketKey) {
                'double_chance' => ['1X', '12', 'X2'],
                'totals:2.5' => ['Over', 'Under'],
                'btts' => ['Yes', 'No'],
                default => ['1', 'X', '2'],
            },
        ]);
    }

    /** "Today", "Tomorrow" or "Saturday 8 Nov", in Nairobi time. */
    public static function dayName(string $date): string
    {
        $tz = (string) config('sportsbook.timezone');
        $today = now($tz)->toDateString();

        return match ($date) {
            $today => 'Today',
            now($tz)->addDay()->toDateString() => 'Tomorrow',
            default => Carbon::parse($date, $tz)->format('l j M'),
        };
    }
}
