<?php

namespace App\Livewire\Predict;

use App\Predict\Data\Market;
use App\Predict\MarketFeed;
use App\Predict\Markets;
use App\Support\PredictPages;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /predictions: "World markets" (imported from Polymarket) first, then "Kadi markets" (our own) when there are any.
 * Category and sort live in the query string. The world list grows with "Load more": it shows the API's pages 1 to
 * $pages, each cached on its own (Markets::list()), so loading more costs one call for the new page only. Markets of
 * one event (one per team under "EPL: 2027 Champion") are grouped into one card across every loaded page. Odds
 * update live from the feed's broadcast (resources/js/predict/live.js); the 25 s poll is the fallback and re-renders
 * only when the feed has moved.
 */
class Lobby extends Component
{
    /** The most pages one visit loads (24 markets each); past this, a filter is the better way in. */
    public const MAX_PAGES = 30;

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: 'closing_soon')]
    public string $sort = 'closing_soon';

    /** How many pages of the world list are shown. */
    #[Locked]
    public int $pages = 1;

    /** The feed version this render showed. */
    #[Locked]
    public int $seenVersion = 0;

    public function setCategory(string $category): void
    {
        $this->category = in_array($category, (array) config('predict.categories'), true) ? $category : '';
        $this->pages = 1;
    }

    public function setSort(string $sort): void
    {
        $this->sort = in_array($sort, (array) config('predict.sorts'), true) ? $sort : (string) config('predict.default_sort');
        $this->pages = 1;
    }

    public function loadMore(): void
    {
        $this->pages = min($this->pages + 1, self::MAX_PAGES);
    }

    /** The poll's fallback: nothing to redraw while the feed has not moved. */
    public function sync(MarketFeed $feed): void
    {
        if ($feed->version() === $this->seenVersion) {
            $this->skipRender();
        }
    }

    public function render(Markets $markets, MarketFeed $feed): View
    {
        $this->seenVersion = $feed->version();
        $filters = ['category' => $this->category, 'sort' => $this->sort];
        $world = $this->worldPages($markets, $filters);

        // The API takes about a second a page: read the next one after this response, so "Load more" finds it ready.
        if ($world['more']) {
            $next = $filters + ['source' => 'polymarket', 'page' => $this->pages + 1];
            dispatch(fn () => app(Markets::class)->warm($next))->afterResponse();
        }
        $kadi = $markets->list($filters + ['source' => 'custom', 'page' => 1]);

        return view('livewire.predict.lobby', [
            'world' => $world,
            'worldGroups' => self::group($world['markets']),
            'kadiGroups' => self::group($kadi['markets']),
            'kadiTotal' => $kadi['total'],
            'categories' => (array) config('predict.categories'),
            'sorts' => (array) config('predict.sorts'),
            'stale' => $feed->isStale(),
            'canBet' => PredictPages::bettingFor(auth()->user()),
            'now' => Date::now(),
        ])
            ->layout('layouts.site', ['shell' => 'app', 'noindex' => true, 'page' => 'predictions', 'predictPanel' => true])
            ->title('Predictions · Kadi');
    }

    /**
     * Pages 1 to $pages of the world list, one after the other. A market that moved onto a later page between reads
     * (the list is live) shows once. Stops at the API's last page, or at a page it cannot read now.
     *
     * @param  array{category: string, sort: string}  $filters
     * @return array{markets: list<Market>, total: int, available: bool, more: bool}
     */
    private function worldPages(Markets $markets, array $filters): array
    {
        $loaded = [];
        $first = null;
        $lastPage = 1;

        for ($page = 1; $page <= $this->pages; $page++) {
            $list = $markets->list($filters + ['source' => 'polymarket', 'page' => $page]);
            $first ??= $list;

            if (! $list['available']) {
                break;
            }

            $lastPage = $list['last_page'];

            foreach ($list['markets'] as $market) {
                $loaded[$market->id] ??= $market;
            }

            if ($page >= $lastPage) {
                break;
            }
        }

        $this->pages = min($this->pages, max(1, $lastPage));

        return [
            'markets' => array_values($loaded),
            'total' => $first['total'],
            'available' => $first['available'],
            'more' => $this->pages < $lastPage && $this->pages < self::MAX_PAGES,
        ];
    }

    /**
     * Markets of the same event together, in the order the API listed them (an event sits where its first market
     * was).
     *
     * @param  list<Market>  $markets
     * @return list<array{title: ?string, markets: list<Market>}>
     */
    public static function group(array $markets): array
    {
        $groups = [];

        foreach ($markets as $market) {
            $key = $market->eventTitle !== null ? 'e:'.$market->eventTitle : 'm:'.$market->id;
            $groups[$key] ??= ['title' => $market->eventTitle, 'markets' => []];
            $groups[$key]['markets'][] = $market;
        }

        return array_values($groups);
    }
}
