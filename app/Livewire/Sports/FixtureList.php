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
 * A sport's match list (plan §2): tabs (Highlights, Today, Tomorrow, This week), a market switcher, and rows grouped by
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

    /** The sport listed (a config sportsbook.sports slug); its markets set the switcher and the columns. */
    #[Locked]
    public string $sport = 'football';

    #[Locked]
    public string $icon = 'ball';

    public function mount(?int $competitionId = null, bool $preview = false, string $tab = 'highlights', string $heading = 'Football', string $sport = 'football', string $icon = 'ball'): void
    {
        $this->heading = $heading;
        $this->sport = $sport;
        $this->icon = $icon;
        $this->tab = array_key_exists($tab, FixtureListing::TABS) ? $tab : 'highlights';
        $this->competitionId = $competitionId;
        $this->preview = $preview;
        $this->shown = $preview ? 10 : self::BATCH;
    }

    public function updated(string $property): void
    {
        $this->tab = array_key_exists($this->tab, FixtureListing::TABS) ? $this->tab : 'highlights';
        $this->marketKey = array_key_exists($this->marketKey, FixtureListing::listMarkets($this->sport)) ? $this->marketKey : 'h2h';

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
        // A competition page lists that competition only; otherwise the sport's matches.
        return app(FixtureListing::class)->forTab($this->tab, $this->marketKey, $this->competition, $this->shown, $this->competition === null ? $this->sport : null);
    }

    #[Computed]
    public function total(): int
    {
        return app(FixtureListing::class)->count($this->tab, $this->competition, $this->competition === null ? $this->sport : null);
    }

    public function render(): View
    {
        $tz = (string) config('sportsbook.timezone');
        $twoWay = FixtureListing::isTwoWay($this->sport);

        return view('livewire.sports.fixture-list', [
            'groups' => $this->fixtures->groupBy(fn (Fixture $fixture): string => $fixture->kicks_off_at->timezone($tz)->toDateString().'|'.$fixture->competition->name),
            'markets' => FixtureListing::listMarkets($this->sport),
            'columns' => $twoWay || ! in_array($this->marketKey, ['h2h', 'double_chance'], true) ? 2 : 3,
            'labels' => match ($this->marketKey) {
                'double_chance' => ['1X', '12', 'X2'],
                'totals:2.5', 'totals' => ['Over', 'Under'],
                'btts' => ['Yes', 'No'],
                default => $twoWay ? ['1', '2'] : ['1', 'X', '2'],
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
