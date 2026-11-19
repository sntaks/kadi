<?php

namespace App\Livewire\Predict;

use App\Predict\Data\Market;
use App\Predict\Markets;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;
use Livewire\Component;

/**
 * The landing's prediction shelf: four Local (`source=custom`) politics markets the API ranks as trending (its own
 * `trending` sort, never a figure of ours), one per event so a single event cannot fill the row. Rendered lazily after the page, so the landing
 * never waits on the API; markets open in the prediction sheet.
 */
class Trending extends Component
{
    public const COUNT = 4;

    /** The one list the shelf reads; Markets::warmLobby() keeps it warm. */
    public const FILTERS = ['source' => 'custom', 'category' => 'politics', 'sort' => 'trending'];

    public function placeholder(): View
    {
        return view('livewire.predict.trending-placeholder');
    }

    public function render(Markets $markets): View
    {
        return view('livewire.predict.trending', [
            'markets' => self::pick($markets->list(self::FILTERS)['markets']),
            'now' => Date::now(),
        ]);
    }

    /**
     * @param  list<Market>  $markets
     * @return list<Market>
     */
    public static function pick(array $markets): array
    {
        $picked = [];
        $events = [];

        foreach ($markets as $market) {
            // The API filters already; this keeps a World or other-category market off the shelf if it ever doesn't.
            if ($market->isImported() || $market->category !== self::FILTERS['category']) {
                continue;
            }

            if ($market->eventTitle !== null && isset($events[$market->eventTitle])) {
                continue;
            }

            $events[(string) $market->eventTitle] = true;
            $picked[] = $market;

            if (count($picked) === self::COUNT) {
                break;
            }
        }

        return $picked;
    }
}
