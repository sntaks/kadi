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
 * /predictions: "Local markets" (our own, source=custom) first when there are any, then "World markets" (imported
 * from Polymarket). ?source=local or ?source=world shows (and reads) only that section, with its own empty states.
 * Source, category and sort live in the query string. Each list grows with its own "Load more": it shows the API's pages 1 to
 * $kadiPages / $worldPages, each cached on its own (Markets::list()), so loading more costs one call for the new page
 * only. Markets of one event (one per team under "EPL: 2027 Champion") are grouped into one card across every loaded
 * page of their list. Odds
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

    /** '' (both sections), or a key of Markets::SECTIONS (local, world). */
    #[Url(except: '')]
    public string $source = '';

    /** How many pages of the Kadi list are shown. */
    #[Locked]
    public int $kadiPages = 1;

    /** How many pages of the world list are shown. */
    #[Locked]
    public int $worldPages = 1;

    /** The feed version this render showed. */
    #[Locked]
    public int $seenVersion = 0;

    public function setCategory(string $category): void
    {
        $this->category = in_array($category, (array) config('predict.categories'), true) ? $category : '';
        $this->kadiPages = 1;
        $this->worldPages = 1;
    }

    public function setSort(string $sort): void
    {
        $this->sort = in_array($sort, (array) config('predict.sorts'), true) ? $sort : (string) config('predict.default_sort');
        $this->kadiPages = 1;
        $this->worldPages = 1;
    }

    public function setSource(string $source): void
    {
        $this->source = array_key_exists($source, Markets::SECTIONS) ? $source : '';
        $this->kadiPages = 1;
        $this->worldPages = 1;
    }

    public function loadMoreKadi(): void
    {
        $this->kadiPages = min($this->kadiPages + 1, self::MAX_PAGES);
    }

    public function loadMoreWorld(): void
    {
        $this->worldPages = min($this->worldPages + 1, self::MAX_PAGES);
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
        // The query string is the player's to edit: anything but a known section shows both.
        $source = array_key_exists($this->source, Markets::SECTIONS) ? $this->source : '';
        $none = ['markets' => [], 'total' => 0, 'available' => true, 'more' => false, 'pages' => 1];

        $kadi = $source !== 'world' ? $this->sourcePages($markets, $filters, 'custom', $this->kadiPages) : $none;
        $this->kadiPages = $kadi['pages'];
        $world = $source !== 'local' ? $this->sourcePages($markets, $filters, 'polymarket', $this->worldPages) : $none;
        $this->worldPages = $world['pages'];

        // The API takes about a second a page: read the next one after this response, so "Load more" finds it ready.
        // A hidden (empty) Kadi section needs no next page.
        $next = [];
        if ($kadi['more'] && $kadi['markets'] !== []) {
            $next[] = $filters + ['source' => 'custom', 'page' => $this->kadiPages + 1];
        }
        if ($world['more']) {
            $next[] = $filters + ['source' => 'polymarket', 'page' => $this->worldPages + 1];
        }
        if ($next !== []) {
            dispatch(function () use ($next) {
                foreach ($next as $filters) {
                    app(Markets::class)->warm($filters);
                }
            })->afterResponse();
        }

        return view('livewire.predict.lobby', [
            'kadi' => $kadi,
            'kadiGroups' => self::group($kadi['markets']),
            'world' => $world,
            'worldGroups' => self::group($world['markets']),
            'shown' => $source,
            'sections' => array_keys(Markets::SECTIONS),
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
     * Pages 1 to $pages of one source's list, one after the other. A market that moved onto a later page between
     * reads (the list is live) shows once. Stops at the API's last page, or at a page it cannot read now. `pages` is
     * $pages clamped to the API's last page.
     *
     * @param  array{category: string, sort: string}  $filters
     * @param  'custom'|'polymarket'  $source
     * @return array{markets: list<Market>, total: int, available: bool, more: bool, pages: int}
     */
    private function sourcePages(Markets $markets, array $filters, string $source, int $pages): array
    {
        $loaded = [];
        $first = null;
        $lastPage = 1;

        for ($page = 1; $page <= $pages; $page++) {
            $list = $markets->list($filters + ['source' => $source, 'page' => $page]);
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

        $pages = min($pages, max(1, $lastPage));

        return [
            'markets' => array_values($loaded),
            'total' => $first['total'],
            'available' => $first['available'],
            'more' => $pages < $lastPage && $pages < self::MAX_PAGES,
            'pages' => $pages,
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
